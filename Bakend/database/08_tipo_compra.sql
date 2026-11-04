-- =====================================================================
-- 08 · Tipo de compra (CONTADO o CREDITO) en la tabla compra (CU15)
-- ---------------------------------------------------------------------
-- Para una base ya instalada. En una instalación nueva no hace falta:
-- 01_esquema.sql ya crea la columna y 06 ya trae la función con p_tipo.
--
--   php Bakend/database/ejecutar.php 08_tipo_compra.sql
-- =====================================================================

set search_path = mitsu, public;

-- 1) Nueva columna: las compras que ya existen quedan como CONTADO
alter table compra add column if not exists tipo varchar(10) not null default 'CONTADO'
    check (tipo in ('CONTADO', 'CREDITO'));

-- 2) La función cambia de parámetros: se quita la versión de 5 parámetros
drop function if exists mitsu.fn_registrar_compra(integer, integer, date, text, jsonb);

-- 3) Misma función que en 06_procedimientos_triggers.sql, con p_tipo
create or replace function mitsu.fn_registrar_compra(
    p_usuario integer, p_proveedor integer, p_fecha date, p_observacion text, p_items jsonb, p_tipo text default 'CONTADO')
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

    if p_tipo is null or p_tipo not in ('CONTADO', 'CREDITO') then
        raise exception 'El tipo de compra debe ser CONTADO o CREDITO.';
    end if;

    -- Versión anterior (sin tipo de compra):
    -- insert into compra (proveedor_id, usuario_id, fecha, observacion)
    -- values (p_proveedor, p_usuario, p_fecha, nullif(trim(coalesce(p_observacion, '')), ''))
    -- returning id, numero into v_compra, v_numero;
    insert into compra (proveedor_id, usuario_id, fecha, tipo, observacion)
    values (p_proveedor, p_usuario, p_fecha, p_tipo, nullif(trim(coalesce(p_observacion, '')), ''))
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

grant execute on function mitsu.fn_registrar_compra(integer, integer, date, text, jsonb, text) to mitsu_app;
