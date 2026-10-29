-- =====================================================================
-- Taller de Cerámica Mitsü
-- 06_procedimientos_triggers.sql · Procedimientos almacenados (PL/pgSQL)
-- ---------------------------------------------------------------------
-- Cada procedimiento hace una operación completa dentro de una sola
-- transacción: si un paso falla, no queda nada a medias.
-- Las reglas de stock, cupo y caja las vuelven a revisar los triggers
-- de 02_reglas_negocio.sql.
-- =====================================================================

set search_path = mitsu, extensions, public;

-- ---------------------------------------------------------------------
-- PA1 · Registrar una venta en caja (CU18)
-- p_items: [{"pieza_id": 1, "cantidad": 2}, ...]
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_registrar_venta(
    p_usuario integer, p_cliente integer, p_metodo integer, p_recibido numeric, p_items jsonb)
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v_caja   integer;
    v_venta  integer;
    v_numero varchar;
    v_total  numeric(12,2);
    v_item   record;
    v_pieza  pieza%rowtype;
begin
    if p_items is null or jsonb_typeof(p_items) <> 'array' or jsonb_array_length(p_items) = 0 then
        raise exception 'Agregue al menos una pieza a la venta.';
    end if;

    select id into v_caja from caja where estado = 'ABIERTA' for update;
    if v_caja is null then
        raise exception 'No hay una caja abierta. Abra la caja antes de vender.';
    end if;
    if not exists (select 1 from cliente where id = p_cliente and estado = 'ACTIVO') then
        raise exception 'El cliente no está activo.';
    end if;
    if not exists (select 1 from metodo_pago where id = p_metodo and estado = 'ACTIVO') then
        raise exception 'Método de pago no válido.';
    end if;

    insert into venta (caja_id, cliente_id, usuario_id, metodo_pago_id)
    values (v_caja, p_cliente, p_usuario, p_metodo)
    returning id, numero into v_venta, v_numero;

    -- Se recorren las piezas en orden de id para bloquearlas siempre en el mismo orden
    for v_item in
        select (e->>'pieza_id')::integer as pieza_id, sum((e->>'cantidad')::integer) as cantidad
          from jsonb_array_elements(p_items) e
         group by 1 order by 1
    loop
        if v_item.cantidad is null or v_item.cantidad <= 0 then
            raise exception 'La cantidad debe ser mayor a cero.';
        end if;
        select * into v_pieza from pieza where id = v_item.pieza_id for update;
        if not found then
            raise exception 'Una de las piezas no existe.';
        end if;
        if v_pieza.stock < v_item.cantidad then
            raise exception 'Stock insuficiente de "%": quedan % unidades.', v_pieza.nombre, v_pieza.stock;
        end if;
        insert into detalle_venta (venta_id, pieza_id, cantidad, precio_unitario)
        values (v_venta, v_pieza.id, v_item.cantidad, v_pieza.precio);
        insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, venta_id, usuario_id)
        values (v_pieza.id, 'VENTA', v_item.cantidad, 'Venta ' || v_numero, v_venta, p_usuario);
    end loop;

    select total into v_total from venta where id = v_venta;
    if p_metodo = (select id from metodo_pago where normalizar(nombre) = 'efectivo')
       and coalesce(p_recibido, 0) < v_total then
        raise exception 'El monto recibido (Bs %) es menor al total (Bs %).', coalesce(p_recibido, 0), v_total;
    end if;
    update venta set monto_recibido = coalesce(p_recibido, v_total) where id = v_venta;
    return v_venta;
end $$;

-- ---------------------------------------------------------------------
-- PA2 · Anular una venta con motivo: devuelve el stock (CU18)
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_anular_venta(p_venta integer, p_usuario integer, p_motivo text)
returns void
language plpgsql
set search_path = mitsu, public
as $$
declare
    v venta%rowtype;
begin
    select * into v from venta where id = p_venta for update;
    if not found then
        raise exception 'La venta no existe.';
    end if;
    if v.estado = 'ANULADA' then
        raise exception 'La venta % ya estaba anulada.', v.numero;
    end if;
    if length(trim(coalesce(p_motivo, ''))) < 5 then
        raise exception 'Escriba el motivo de la anulación (mínimo 5 caracteres).';
    end if;

    insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, venta_id, usuario_id)
    select pieza_id, 'ANULACION', cantidad, 'Anulación de ' || v.numero, p_venta, p_usuario
      from detalle_venta where venta_id = p_venta order by pieza_id;

    update venta set estado = 'ANULADA', motivo_anulacion = trim(p_motivo), anulado_por = p_usuario
     where id = p_venta;
end $$;

-- ---------------------------------------------------------------------
-- PA3 · Registrar una compra: suma stock y recalcula el costo promedio (CU15)
-- p_items: [{"material_id": 1, "cantidad": 50, "costo": 6.5}, ...]
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_registrar_compra(
    p_usuario integer, p_proveedor integer, p_fecha date, p_observacion text, p_items jsonb)
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v_compra integer;
    v_numero varchar;
    v_item   record;
    v_mat    material%rowtype;
begin
    if p_items is null or jsonb_typeof(p_items) <> 'array' or jsonb_array_length(p_items) = 0 then
        raise exception 'Agregue al menos un material a la compra.';
    end if;
    if not exists (select 1 from proveedor where id = p_proveedor and estado = 'ACTIVO') then
        raise exception 'El proveedor no está activo.';
    end if;
    if p_fecha > hoy_bo() or p_fecha < hoy_bo() - 60 then
        raise exception 'La fecha de compra debe estar entre los últimos 60 días y hoy.';
    end if;

    insert into compra (proveedor_id, usuario_id, fecha, observacion)
    values (p_proveedor, p_usuario, p_fecha, nullif(trim(coalesce(p_observacion, '')), ''))
    returning id, numero into v_compra, v_numero;

    for v_item in
        select (e->>'material_id')::integer as material_id,
               (e->>'cantidad')::numeric as cantidad,
               (e->>'costo')::numeric as costo
          from jsonb_array_elements(p_items) e
         order by 1
    loop
        select * into v_mat from material where id = v_item.material_id for update;
        if not found or v_mat.estado <> 'ACTIVO' then
            raise exception 'Uno de los materiales no existe o está inactivo.';
        end if;
        if v_item.cantidad is null or v_item.cantidad <= 0 or v_item.costo is null or v_item.costo < 0 then
            raise exception 'Cantidad y costo de "%" deben ser válidos.', v_mat.nombre;
        end if;

        insert into detalle_compra (compra_id, material_id, cantidad, costo_unitario)
        values (v_compra, v_mat.id, v_item.cantidad, v_item.costo);

        -- Costo promedio ponderado con el stock que había antes
        update material
           set costo_promedio = round((v_mat.stock * v_mat.costo_promedio + v_item.cantidad * v_item.costo)
                                      / (v_mat.stock + v_item.cantidad), 2)
         where id = v_mat.id;

        insert into movimiento_material (material_id, tipo, cantidad, motivo, compra_id, usuario_id)
        values (v_mat.id, 'ENTRADA', v_item.cantidad, 'Compra ' || v_numero, v_compra, p_usuario);
    end loop;
    return v_compra;
end $$;

-- ---------------------------------------------------------------------
-- PA4 · Anular una compra: descuenta lo que había ingresado (CU15)
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_anular_compra(p_compra integer, p_usuario integer, p_motivo text)
returns void
language plpgsql
set search_path = mitsu, public
as $$
declare
    c compra%rowtype;
begin
    select * into c from compra where id = p_compra for update;
    if not found then
        raise exception 'La compra no existe.';
    end if;
    if c.estado = 'ANULADA' then
        raise exception 'La compra % ya estaba anulada.', c.numero;
    end if;
    if length(trim(coalesce(p_motivo, ''))) < 5 then
        raise exception 'Escriba el motivo de la anulación (mínimo 5 caracteres).';
    end if;
    -- Si el material ya se consumió, el trigger del kardex impide dejar stock negativo
    insert into movimiento_material (material_id, tipo, cantidad, motivo, compra_id, usuario_id)
    select material_id, 'AJUSTE_MENOS', cantidad, 'Anulación de ' || c.numero, p_compra, p_usuario
      from detalle_compra where compra_id = p_compra order by material_id;
    update compra set estado = 'ANULADA', motivo_anulacion = trim(p_motivo) where id = p_compra;
end $$;

-- ---------------------------------------------------------------------
-- PA5 · Reservar una clase (CU10): el trigger valida cupo y fecha
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_reservar_clase(p_sesion integer, p_alumno integer, p_usuario integer)
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v_id integer;
begin
    insert into reserva (sesion_id, alumno_id, registrado_por)
    values (p_sesion, p_alumno, p_usuario)
    returning id into v_id;
    return v_id;
end $$;

-- ---------------------------------------------------------------------
-- PA6 · Generar las clases del calendario a partir de los horarios (CU09)
-- No duplica: si la clase de ese horario y fecha ya existe, la salta.
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_generar_sesiones(p_desde date, p_hasta date)
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v_total integer;
begin
    if p_hasta < p_desde then
        raise exception 'La fecha final debe ser posterior a la inicial.';
    end if;
    if p_hasta - p_desde > 92 then
        raise exception 'Genere como máximo tres meses de clases por vez.';
    end if;
    insert into sesion_clase (horario_id, maestra_id, fecha, hora_inicio, hora_fin, cupo)
    select h.id, h.maestra_id, d::date, h.hora_inicio, h.hora_fin, h.cupo
      from horario h
      join taller t on t.id = h.taller_id and t.estado = 'ACTIVO'
     cross join generate_series(greatest(p_desde, hoy_bo()), p_hasta, interval '1 day') d
     where h.estado = 'ACTIVO' and extract(isodow from d) = h.dia_semana
    on conflict (horario_id, fecha) do nothing;
    get diagnostics v_total = row_count;
    return v_total;
end $$;

-- ---------------------------------------------------------------------
-- PA7 · Cerrar la caja con arqueo (CU19) — procedimiento (CALL)
-- ---------------------------------------------------------------------
create or replace procedure mitsu.sp_cerrar_caja(
    p_caja integer, p_usuario integer, p_declarado numeric, p_observacion text)
language plpgsql
set search_path = mitsu, public
as $$
declare
    c          caja%rowtype;
    v_efectivo numeric(12,2);
    v_otros    numeric(12,2);
begin
    select * into c from caja where id = p_caja for update;
    if not found or c.estado <> 'ABIERTA' then
        raise exception 'La caja no está abierta.';
    end if;
    if p_declarado is null or p_declarado < 0 then
        raise exception 'Ingrese el efectivo contado en caja.';
    end if;
    select coalesce(sum(v.total) filter (where normalizar(m.nombre) = 'efectivo'), 0),
           coalesce(sum(v.total) filter (where normalizar(m.nombre) <> 'efectivo'), 0)
      into v_efectivo, v_otros
      from venta v join metodo_pago m on m.id = v.metodo_pago_id
     where v.caja_id = p_caja and v.estado = 'VALIDA';

    update caja
       set total_efectivo  = v_efectivo,
           total_otros     = v_otros,
           monto_declarado = p_declarado,
           diferencia      = p_declarado - (c.monto_inicial + v_efectivo),
           cerrada_en      = now(),
           cerrada_por     = p_usuario,
           estado          = 'CERRADA',
           observacion     = nullif(trim(coalesce(p_observacion, '')), '')
     where id = p_caja;
end $$;

-- ---------------------------------------------------------------------
-- Trigger de auditoría: toda anulación queda en la bitácora
-- aunque se haga directamente en la base de datos
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_auditar_anulacion()
returns trigger
language plpgsql
set search_path = mitsu, public
as $$
begin
    if new.estado = 'ANULADA' and old.estado <> 'ANULADA' then
        insert into bitacora (usuario_id, accion, modulo, tabla, registro, detalle)
        values (coalesce(usuario_actual(), (to_jsonb(new) ->> 'anulado_por')::integer),
                'ANULAR', case when tg_table_name = 'venta' then 'VENTAS' else 'INVENTARIO' end,
                tg_table_name, new.id, left(new.numero || ': ' || new.motivo_anulacion, 500));
    end if;
    return null;
end $$;

create trigger tg_auditar_venta  after update of estado on venta  for each row execute function tg_auditar_anulacion();
create trigger tg_auditar_compra after update of estado on compra for each row execute function tg_auditar_anulacion();
