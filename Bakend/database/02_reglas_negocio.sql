-- =====================================================================
-- Taller de Cerámica Mitsü
-- 02_reglas_negocio.sql · Triggers (última línea de defensa)
-- ---------------------------------------------------------------------
-- PHP ya valida todo, pero estas reglas se cumplen aunque alguien
-- escriba directamente en la base de datos.
-- =====================================================================

set search_path = mitsu, extensions, public;

-- Usuario que hace la operación (PHP lo fija con set_config dentro de la transacción)
create or replace function mitsu.usuario_actual()
returns integer
language sql stable
as $$ select nullif(current_setting('mitsu.usuario_id', true), '')::integer $$;

-- ---------------------------------------------------------------------
-- RN01 · usuario.actualizado_en se mantiene solo
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_tocar_actualizado()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    new.actualizado_en := now();
    return new;
end $$;

create trigger tg_usuario_actualizado
    before update on usuario
    for each row execute function tg_tocar_actualizado();

-- ---------------------------------------------------------------------
-- RN02 · Siempre debe quedar al menos un administrador activo
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_ultimo_admin()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare
    v_admin smallint := (select id from rol where nombre = 'ADMINISTRADOR');
begin
    if old.rol_id = v_admin and old.estado = 'ACTIVO'
       and (new.estado <> 'ACTIVO' or new.rol_id <> v_admin)
       and not exists (select 1 from usuario where rol_id = v_admin and estado = 'ACTIVO' and id <> old.id) then
        raise exception 'No se puede desactivar ni cambiar de rol al único administrador activo.';
    end if;
    return new;
end $$;

create trigger tg_usuario_ultimo_admin
    before update of estado, rol_id on usuario
    for each row execute function tg_ultimo_admin();

-- ---------------------------------------------------------------------
-- RN03 · Un horario solo se asigna a una maestra y un taller activos
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_horario_valido()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    if new.estado = 'ACTIVO' then
        if not exists (select 1 from maestra m join usuario u on u.id = m.usuario_id
                       where m.id = new.maestra_id and m.estado = 'ACTIVO' and u.estado = 'ACTIVO') then
            raise exception 'La maestra seleccionada no está activa.';
        end if;
        if not exists (select 1 from taller where id = new.taller_id and estado = 'ACTIVO') then
            raise exception 'El taller seleccionado no está activo.';
        end if;
    end if;
    return new;
end $$;

create trigger tg_horario_valido
    before insert or update on horario
    for each row execute function tg_horario_valido();

-- ---------------------------------------------------------------------
-- RN04 · Reservas: con cupo, sin repetir, solo clases futuras y programadas
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_reserva_validar()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare
    s       sesion_clase%rowtype;
    ocupado integer;
begin
    -- Se bloquea la fila de la sesión: dos reservas simultáneas no pueden pasar el cupo
    select * into s from sesion_clase where id = new.sesion_id for update;

    if tg_op = 'INSERT' then
        if s.estado <> 'PROGRAMADA' then
            raise exception 'La clase no está disponible para reservas.';
        end if;
        if s.fecha < hoy_bo() or (s.fecha = hoy_bo() and s.hora_inicio <= (now() at time zone 'America/La_Paz')::time) then
            raise exception 'No se puede reservar una clase que ya empezó o pasó.';
        end if;
        if not exists (select 1 from alumno a join usuario u on u.id = a.usuario_id
                       where a.id = new.alumno_id and a.estado = 'ACTIVO' and u.estado = 'ACTIVO') then
            raise exception 'El alumno no está activo.';
        end if;
        if exists (select 1 from reserva where sesion_id = new.sesion_id and alumno_id = new.alumno_id and estado <> 'CANCELADA') then
            raise exception 'El alumno ya tiene una reserva en esta clase.';
        end if;
        select count(*) into ocupado from reserva where sesion_id = new.sesion_id and estado <> 'CANCELADA';
        if ocupado >= s.cupo then
            raise exception 'La clase ya no tiene cupos disponibles.';
        end if;
        new.estado := 'RESERVADA';
        return new;
    end if;

    -- UPDATE: transiciones permitidas
    if old.estado = new.estado then
        return new;
    end if;
    if old.estado = 'CANCELADA' then
        raise exception 'Una reserva cancelada no se puede modificar.';
    end if;
    if new.estado = 'CANCELADA' then
        if old.estado <> 'RESERVADA' then
            raise exception 'Solo se cancelan reservas pendientes, no las que ya tienen asistencia.';
        end if;
        new.cancelado_en := now();
    elsif new.estado in ('ASISTIO', 'FALTO') then
        if s.fecha > hoy_bo() then
            raise exception 'La asistencia se registra el día de la clase o después.';
        end if;
        if s.estado = 'CANCELADA' then
            raise exception 'La clase fue cancelada.';
        end if;
        new.asistencia_en := now();
    elsif new.estado = 'RESERVADA' then
        raise exception 'No se puede volver la reserva a pendiente.';
    end if;
    return new;
end $$;

create trigger tg_reserva_validar
    before insert or update on reserva
    for each row execute function tg_reserva_validar();

-- ---------------------------------------------------------------------
-- RN05 · Si se cancela una clase, se cancelan sus reservas pendientes
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_sesion_cancelada()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    if new.estado = 'CANCELADA' and old.estado <> 'CANCELADA' then
        update reserva
           set estado = 'CANCELADA', motivo_cancelacion = 'Clase cancelada: ' || new.motivo_cancelacion
         where sesion_id = new.id and estado = 'RESERVADA';
    end if;
    if new.cupo < (select count(*) from reserva where sesion_id = new.id and estado <> 'CANCELADA') then
        raise exception 'El cupo no puede ser menor que las reservas ya hechas.';
    end if;
    return new;
end $$;

create trigger tg_sesion_cancelada
    after update on sesion_clase
    for each row execute function tg_sesion_cancelada();

-- ---------------------------------------------------------------------
-- RN06 · Kardex de materiales: actualiza el stock y nunca lo deja negativo
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_movimiento_material()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare
    v_stock numeric(12,2);
    v_nombre varchar;
begin
    select stock, nombre into v_stock, v_nombre from material where id = new.material_id for update;
    if new.tipo in ('ENTRADA', 'AJUSTE_MAS') then
        v_stock := v_stock + new.cantidad;
    else
        v_stock := v_stock - new.cantidad;
        if v_stock < 0 then
            raise exception 'Stock insuficiente de "%": hay % y se pidió %.', v_nombre, v_stock + new.cantidad, new.cantidad;
        end if;
    end if;
    update material set stock = v_stock where id = new.material_id;
    new.stock_resultante := v_stock;
    return new;
end $$;

create trigger tg_movimiento_material
    before insert on movimiento_material
    for each row execute function tg_movimiento_material();

-- ---------------------------------------------------------------------
-- RN07 · Kardex de piezas: actualiza el stock y nunca lo deja negativo
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_movimiento_pieza()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare
    v_stock integer;
    v_nombre varchar;
begin
    select stock, nombre into v_stock, v_nombre from pieza where id = new.pieza_id for update;
    if new.tipo in ('PRODUCCION', 'ANULACION', 'AJUSTE_MAS') then
        v_stock := v_stock + new.cantidad;
    else
        v_stock := v_stock - new.cantidad;
        if v_stock < 0 then
            raise exception 'Stock insuficiente de "%": quedan % unidades.', v_nombre, v_stock + new.cantidad;
        end if;
    end if;
    update pieza set stock = v_stock where id = new.pieza_id;
    new.stock_resultante := v_stock;
    return new;
end $$;

create trigger tg_movimiento_pieza
    before insert on movimiento_pieza
    for each row execute function tg_movimiento_pieza();

-- El stock solo cambia mediante movimientos (no se edita a mano)
create or replace function mitsu.tg_stock_protegido()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    if new.stock <> old.stock and pg_trigger_depth() = 1 then
        raise exception 'El stock solo cambia con compras, ventas o ajustes registrados.';
    end if;
    return new;
end $$;

create trigger tg_material_stock_protegido before update of stock on material
    for each row execute function tg_stock_protegido();
create trigger tg_pieza_stock_protegido before update of stock on pieza
    for each row execute function tg_stock_protegido();

-- ---------------------------------------------------------------------
-- RN08 · El precio de venta se copia de la pieza y el total se calcula
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_detalle_venta_precio()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare
    p pieza%rowtype;
begin
    select * into p from pieza where id = new.pieza_id;
    if p.estado <> 'ACTIVO' then
        raise exception 'La pieza "%" no está a la venta.', p.nombre;
    end if;
    new.precio_unitario := p.precio;
    return new;
end $$;

create trigger tg_detalle_venta_precio
    before insert on detalle_venta
    for each row execute function tg_detalle_venta_precio();

create or replace function mitsu.tg_total_venta()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    update venta set total = (select coalesce(sum(subtotal), 0) from detalle_venta where venta_id = new.venta_id)
     where id = new.venta_id;
    return null;
end $$;

create trigger tg_total_venta
    after insert on detalle_venta
    for each row execute function tg_total_venta();

create or replace function mitsu.tg_total_compra()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    update compra set total = (select coalesce(sum(subtotal), 0) from detalle_compra where compra_id = new.compra_id)
     where id = new.compra_id;
    return null;
end $$;

create trigger tg_total_compra
    after insert on detalle_compra
    for each row execute function tg_total_compra();

-- ---------------------------------------------------------------------
-- RN09 · Ventas: solo con caja abierta; una venta anulada no se modifica
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_venta_reglas()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    if tg_op = 'INSERT' then
        if not exists (select 1 from caja where id = new.caja_id and estado = 'ABIERTA') then
            raise exception 'No hay una caja abierta. Abra la caja antes de vender.';
        end if;
        return new;
    end if;
    if old.estado = 'ANULADA' then
        raise exception 'La venta % ya está anulada y no se puede modificar.', old.numero;
    end if;
    if new.estado = 'ANULADA' then
        new.anulado_en := now();
    end if;
    return new;
end $$;

create trigger tg_venta_reglas
    before insert or update on venta
    for each row execute function tg_venta_reglas();

-- ---------------------------------------------------------------------
-- RN10 · Historial de precios de las piezas
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_historial_precio()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    if new.precio <> old.precio then
        insert into historial_precio (pieza_id, precio_anterior, precio_nuevo, usuario_id)
        values (new.id, old.precio, new.precio, usuario_actual());
    end if;
    return new;
end $$;

create trigger tg_historial_precio
    after update of precio on pieza
    for each row execute function tg_historial_precio();
