-- =====================================================================
-- Taller de Cerámica Mitsü
-- 07_tienda_pasarela.sql · Ciclo III (ampliación): tienda en línea,
-- pedidos web y pasarela de pago (QR, tarjeta y PayPal)
-- ---------------------------------------------------------------------
-- CU20 Comprar en la tienda en línea · CU21 Pagar en línea ·
-- CU22 Gestionar pedidos en línea
--
-- La pasarela trabaja en MODO DE DEMOSTRACIÓN: no se cobra dinero real.
-- De la tarjeta solo se guardan la marca y los 4 últimos dígitos
-- (nunca el número completo ni el código de seguridad).
-- =====================================================================

set search_path = mitsu, extensions, public;

-- ---------------------------------------------------------------------
-- Rol CLIENTE y permisos nuevos
-- ---------------------------------------------------------------------
insert into rol (nombre, descripcion)
select 'CLIENTE', 'Compra piezas en la tienda en línea y paga con QR, tarjeta o PayPal'
where not exists (select 1 from rol where nombre = 'CLIENTE');

insert into permiso (codigo, descripcion, modulo) values
    ('tienda.comprar',    'Comprar en la tienda en línea y ver sus pedidos', 'P4 Ventas'),
    ('pedidos.gestionar', 'Confirmar pagos QR, entregar y cancelar pedidos web', 'P4 Ventas')
on conflict (codigo) do nothing;

insert into rolpermiso (rol_id, permiso_id)
select r.id, p.id from rol r join permiso p on
      (r.nombre in ('CLIENTE', 'ALUMNO') and p.codigo = 'tienda.comprar')
   or (r.nombre in ('ADMINISTRADOR', 'ADMINISTRATIVO') and p.codigo = 'pedidos.gestionar')
on conflict do nothing;

-- El cliente de la tienda tiene cuenta de usuario (los de mostrador no)
alter table cliente add column if not exists usuario_id integer unique references usuario (id);

-- ---------------------------------------------------------------------
-- Pedido web
-- ---------------------------------------------------------------------
create sequence if not exists pedido_numero_seq;
create table if not exists pedido (
    id                  serial primary key,
    numero              varchar(12)   not null unique default 'PW-' || lpad(nextval('mitsu.pedido_numero_seq')::text, 6, '0'),
    cliente_id          integer       not null references cliente (id),
    usuario_id          integer       not null references usuario (id),
    fecha               timestamptz   not null default now(),
    expira_en           timestamptz   not null default now() + interval '30 minutes',
    entrega             varchar(10)   not null default 'RETIRO' check (entrega in ('RETIRO', 'ENVIO')),
    direccion           varchar(200),
    telefono            varchar(20)   not null check (telefono ~ '^[0-9+\- ]{7,20}$'),
    nota                varchar(300),
    total               numeric(12,2) not null default 0 check (total >= 0),
    estado              varchar(16)   not null default 'PENDIENTE_PAGO'
                        check (estado in ('PENDIENTE_PAGO', 'EN_VERIFICACION', 'PAGADO', 'ENTREGADO', 'CANCELADO')),
    motivo_cancelacion  varchar(255),
    pagado_en           timestamptz,
    entregado_en        timestamptz,
    comprobante_enviado boolean       not null default false,
    check (entrega = 'RETIRO' or direccion is not null),
    check (estado <> 'CANCELADO' or motivo_cancelacion is not null)
);
create index if not exists ix_pedido_usuario on pedido (usuario_id, fecha desc);
create index if not exists ix_pedido_estado on pedido (estado);

create table if not exists detalle_pedido (
    id              serial primary key,
    pedido_id       integer       not null references pedido (id),
    pieza_id        integer       not null references pieza (id),
    cantidad        integer       not null check (cantidad between 1 and 20),
    precio_unitario numeric(10,2) not null check (precio_unitario > 0),
    subtotal        numeric(12,2) generated always as (cantidad * precio_unitario) stored,
    unique (pedido_id, pieza_id)
);

-- Intento de pago (puede haber varios por pedido: uno rechazado y luego otro aprobado)
create table if not exists pago (
    id               serial primary key,
    pedido_id        integer       not null references pedido (id),
    metodo           varchar(10)   not null check (metodo in ('QR', 'TARJETA', 'PAYPAL')),
    monto            numeric(12,2) not null check (monto > 0),
    estado           varchar(10)   not null check (estado in ('PENDIENTE', 'APROBADO', 'RECHAZADO')),
    referencia       varchar(40)   not null,
    tarjeta_marca    varchar(20),
    tarjeta_ultimos4 char(4)       check (tarjeta_ultimos4 ~ '^[0-9]{4}$'),
    titular          varchar(80),
    cuenta_paypal    varchar(120),
    detalle          varchar(200),
    creado_en        timestamptz   not null default now(),
    verificado_por   integer       references usuario (id),
    verificado_en    timestamptz,
    check (metodo <> 'TARJETA' or tarjeta_ultimos4 is not null)
);
create index if not exists ix_pago_pedido on pago (pedido_id);
create unique index if not exists ux_pago_aprobado on pago (pedido_id) where estado = 'APROBADO';

-- El kardex de piezas registra también las ventas web
alter table movimiento_pieza add column if not exists pedido_id integer references pedido (id);
alter table movimiento_pieza alter column tipo type varchar(16);
alter table movimiento_pieza drop constraint if exists movimiento_pieza_tipo_check;
alter table movimiento_pieza add constraint movimiento_pieza_tipo_check
    check (tipo in ('PRODUCCION', 'VENTA', 'ANULACION', 'AJUSTE_MAS', 'AJUSTE_MENOS', 'VENTA_WEB', 'DEVOLUCION_WEB'));

create or replace function mitsu.tg_movimiento_pieza()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare
    v_stock integer;
    v_nombre varchar;
begin
    select stock, nombre into v_stock, v_nombre from pieza where id = new.pieza_id for update;
    if new.tipo in ('PRODUCCION', 'ANULACION', 'AJUSTE_MAS', 'DEVOLUCION_WEB') then
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

-- ---------------------------------------------------------------------
-- Triggers del pedido: precio copiado, total calculado y estados válidos
-- ---------------------------------------------------------------------
create or replace function mitsu.tg_detalle_pedido_precio()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare p pieza%rowtype;
begin
    select * into p from pieza where id = new.pieza_id;
    if p.estado <> 'ACTIVO' then
        raise exception 'La pieza "%" ya no está a la venta.', p.nombre;
    end if;
    new.precio_unitario := p.precio;
    return new;
end $$;

drop trigger if exists tg_detalle_pedido_precio on detalle_pedido;
create trigger tg_detalle_pedido_precio before insert on detalle_pedido
    for each row execute function tg_detalle_pedido_precio();

create or replace function mitsu.tg_total_pedido()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    update pedido set total = (select coalesce(sum(subtotal), 0) from detalle_pedido where pedido_id = new.pedido_id)
     where id = new.pedido_id;
    return null;
end $$;

drop trigger if exists tg_total_pedido on detalle_pedido;
create trigger tg_total_pedido after insert on detalle_pedido
    for each row execute function tg_total_pedido();

-- Ciclo de vida: PENDIENTE_PAGO → EN_VERIFICACION → PAGADO → ENTREGADO, o CANCELADO
create or replace function mitsu.tg_pedido_estado()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
begin
    if old.estado = new.estado then
        return new;
    end if;
    if old.estado in ('ENTREGADO', 'CANCELADO') then
        raise exception 'El pedido % ya está % y no puede cambiar.', old.numero, lower(old.estado);
    end if;
    if not ((old.estado = 'PENDIENTE_PAGO'  and new.estado in ('EN_VERIFICACION', 'PAGADO', 'CANCELADO'))
         or (old.estado = 'EN_VERIFICACION' and new.estado in ('PENDIENTE_PAGO', 'PAGADO', 'CANCELADO'))
         or (old.estado = 'PAGADO'          and new.estado in ('ENTREGADO', 'CANCELADO'))) then
        raise exception 'Cambio de estado no permitido: % → %.', old.estado, new.estado;
    end if;
    if new.estado = 'PAGADO' then
        new.pagado_en := now();
    elsif new.estado = 'ENTREGADO' then
        new.entregado_en := now();
    end if;
    return new;
end $$;

drop trigger if exists tg_pedido_estado on pedido;
create trigger tg_pedido_estado before update of estado on pedido
    for each row execute function tg_pedido_estado();

-- Un pago aprobado debe cubrir exactamente el total del pedido
create or replace function mitsu.tg_pago_validar()
returns trigger language plpgsql
set search_path = mitsu, public
as $$
declare v_total numeric(12,2);
begin
    select total into v_total from pedido where id = new.pedido_id;
    if new.monto <> v_total then
        raise exception 'El monto del pago (Bs %) no coincide con el total del pedido (Bs %).', new.monto, v_total;
    end if;
    return new;
end $$;

drop trigger if exists tg_pago_validar on pago;
create trigger tg_pago_validar before insert on pago
    for each row execute function tg_pago_validar();

-- ---------------------------------------------------------------------
-- PA8 · Crear pedido web: reserva el stock (VENTA_WEB) por 30 minutos
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_crear_pedido(
    p_usuario integer, p_cliente integer, p_entrega text, p_direccion text, p_telefono text, p_nota text, p_items jsonb)
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v_pedido integer;
    v_numero varchar;
    v_item   record;
    v_pieza  pieza%rowtype;
begin
    if p_items is null or jsonb_typeof(p_items) <> 'array' or jsonb_array_length(p_items) = 0 then
        raise exception 'Tu carrito está vacío.';
    end if;
    insert into pedido (cliente_id, usuario_id, entrega, direccion, telefono, nota)
    values (p_cliente, p_usuario, p_entrega, nullif(trim(coalesce(p_direccion, '')), ''), p_telefono, nullif(trim(coalesce(p_nota, '')), ''))
    returning id, numero into v_pedido, v_numero;

    for v_item in
        select (e->>'pieza_id')::integer as pieza_id, sum((e->>'cantidad')::integer) as cantidad
          from jsonb_array_elements(p_items) e group by 1 order by 1
    loop
        select * into v_pieza from pieza where id = v_item.pieza_id for update;
        if not found then
            raise exception 'Una de las piezas no existe.';
        end if;
        if v_pieza.stock < v_item.cantidad then
            raise exception 'Solo quedan % unidad(es) de "%".', v_pieza.stock, v_pieza.nombre;
        end if;
        insert into detalle_pedido (pedido_id, pieza_id, cantidad, precio_unitario) values (v_pedido, v_pieza.id, v_item.cantidad, v_pieza.precio);
        insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, pedido_id, usuario_id)
        values (v_pieza.id, 'VENTA_WEB', v_item.cantidad, 'Pedido web ' || v_numero, v_pedido, p_usuario);
    end loop;
    return v_pedido;
end $$;

-- ---------------------------------------------------------------------
-- PA9 · Registrar un intento de pago (CU21)
--   TARJETA / PAYPAL aprobado → pedido PAGADO
--   QR pendiente             → pedido EN_VERIFICACION (lo confirma el taller)
--   rechazado                → el pedido sigue esperando otro intento
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_registrar_pago(
    p_pedido integer, p_metodo text, p_estado text, p_referencia text,
    p_marca text, p_ultimos4 text, p_titular text, p_cuenta text, p_detalle text)
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v pedido%rowtype;
    v_pago integer;
begin
    select * into v from pedido where id = p_pedido for update;
    if not found then
        raise exception 'El pedido no existe.';
    end if;
    if v.estado <> 'PENDIENTE_PAGO' then
        raise exception 'El pedido % no está esperando pago (estado: %).', v.numero, lower(replace(v.estado, '_', ' '));
    end if;
    if v.expira_en < now() then
        raise exception 'El tiempo para pagar el pedido % venció. Vuelve a armar tu carrito.', v.numero;
    end if;
    insert into pago (pedido_id, metodo, monto, estado, referencia, tarjeta_marca, tarjeta_ultimos4, titular, cuenta_paypal, detalle)
    values (p_pedido, p_metodo, v.total, p_estado, p_referencia, p_marca, p_ultimos4, p_titular, p_cuenta, p_detalle)
    returning id into v_pago;

    if p_estado = 'APROBADO' then
        update pedido set estado = 'PAGADO' where id = p_pedido;
    elsif p_estado = 'PENDIENTE' then
        update pedido set estado = 'EN_VERIFICACION' where id = p_pedido;
    end if;
    return v_pago;
end $$;

-- ---------------------------------------------------------------------
-- PA10 · El taller confirma o rechaza un pago por QR (CU22)
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_verificar_pago_qr(p_pago integer, p_usuario integer, p_aprobar boolean, p_detalle text)
returns void
language plpgsql
set search_path = mitsu, public
as $$
declare
    pg pago%rowtype;
begin
    select * into pg from pago where id = p_pago for update;
    if not found or pg.metodo <> 'QR' or pg.estado <> 'PENDIENTE' then
        raise exception 'Ese pago no está pendiente de verificación.';
    end if;
    perform 1 from pedido where id = pg.pedido_id for update;
    update pago set estado = case when p_aprobar then 'APROBADO' else 'RECHAZADO' end,
                    verificado_por = p_usuario, verificado_en = now(),
                    detalle = coalesce(nullif(trim(coalesce(p_detalle, '')), ''), detalle)
     where id = p_pago;
    update pedido set estado = case when p_aprobar then 'PAGADO' else 'PENDIENTE_PAGO' end,
                      expira_en = case when p_aprobar then expira_en else now() + interval '30 minutes' end
     where id = pg.pedido_id;
end $$;

-- ---------------------------------------------------------------------
-- PA11 · Cancelar un pedido: devuelve el stock reservado
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_cancelar_pedido(p_pedido integer, p_usuario integer, p_motivo text)
returns void
language plpgsql
set search_path = mitsu, public
as $$
declare
    v pedido%rowtype;
begin
    select * into v from pedido where id = p_pedido for update;
    if not found then
        raise exception 'El pedido no existe.';
    end if;
    if v.estado in ('ENTREGADO', 'CANCELADO') then
        raise exception 'El pedido % ya está % y no se puede cancelar.', v.numero, lower(v.estado);
    end if;
    if length(trim(coalesce(p_motivo, ''))) < 5 then
        raise exception 'Escribe el motivo de la cancelación (mínimo 5 caracteres).';
    end if;
    insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, pedido_id, usuario_id)
    select pieza_id, 'DEVOLUCION_WEB', cantidad, 'Cancelación de ' || v.numero, p_pedido, p_usuario
      from detalle_pedido where pedido_id = p_pedido order by pieza_id;
    update pago set estado = 'RECHAZADO', detalle = 'Pedido cancelado' where pedido_id = p_pedido and estado = 'PENDIENTE';
    update pedido set estado = 'CANCELADO', motivo_cancelacion = trim(p_motivo) where id = p_pedido;
end $$;

-- ---------------------------------------------------------------------
-- PA12 · Libera el stock de los pedidos que no se pagaron a tiempo
-- ---------------------------------------------------------------------
create or replace function mitsu.fn_vencer_pedidos()
returns integer
language plpgsql
set search_path = mitsu, public
as $$
declare
    v record;
    n integer := 0;
begin
    for v in select id, usuario_id from pedido where estado = 'PENDIENTE_PAGO' and expira_en < now() for update skip locked loop
        perform fn_cancelar_pedido(v.id, v.usuario_id, 'No se pagó dentro de los 30 minutos');
        n := n + 1;
    end loop;
    return n;
end $$;

-- ---------------------------------------------------------------------
-- Permisos y RLS de las tablas nuevas para el usuario de la aplicación
-- ---------------------------------------------------------------------
grant select, insert, update on pedido, detalle_pedido, pago to mitsu_app;
grant usage, select on all sequences in schema mitsu to mitsu_app;
grant execute on all functions in schema mitsu to mitsu_app;
do $$
declare t text;
begin
    foreach t in array array['pedido', 'detalle_pedido', 'pago'] loop
        execute format('alter table mitsu.%I enable row level security', t);
        execute format('drop policy if exists app_acceso on mitsu.%I', t);
        execute format('create policy app_acceso on mitsu.%I for all to mitsu_app using (true) with check (true)', t);
    end loop;
end $$;

-- ---------------------------------------------------------------------
-- Datos de demostración: una clienta con cuenta y dos pedidos web
--   cliente@mitsu.test / Cliente2026!   (contraseña inventada)
-- ---------------------------------------------------------------------
do $$
declare
    v_usuario integer;
    v_cliente integer;
    v_pedido  integer;
begin
    if exists (select 1 from usuario where correo = 'cliente@mitsu.test') then
        return;
    end if;
    insert into usuario (rol_id, nombre, apellido, correo, telefono, clave_hash)
    values ((select id from rol where nombre = 'CLIENTE'), 'Daniela', 'Rivero', 'cliente@mitsu.test', '72040001',
            crypt('Cliente2026!', gen_salt('bf', 10)))
    returning id into v_usuario;
    insert into cliente (nombre, ci_nit, telefono, correo, usuario_id)
    values ('Daniela Rivero', '7012345', '72040001', 'cliente@mitsu.test', v_usuario)
    returning id into v_cliente;

    -- Pedido pagado con tarjeta (listo para retirar)
    v_pedido := fn_crear_pedido(v_usuario, v_cliente, 'RETIRO', null, '72040001', 'Para regalo, por favor',
                                '[{"pieza_id": 1, "cantidad": 1}]'::jsonb);
    perform fn_registrar_pago(v_pedido, 'TARJETA', 'APROBADO', 'AUT-' || lpad((v_pedido * 7919 % 1000000)::text, 6, '0'),
                              'Visa', '4242', 'DANIELA RIVERO', null, 'Aprobado (modo demostración)');

    -- Pedido pagado por QR, esperando que el taller confirme el depósito
    v_pedido := fn_crear_pedido(v_usuario, v_cliente, 'ENVIO', 'Av. Banzer, 4to anillo, calle 3 #120', '72040001', null,
                                '[{"pieza_id": 2, "cantidad": 2}]'::jsonb);
    perform fn_registrar_pago(v_pedido, 'QR', 'PENDIENTE', 'QR-' || lpad((v_pedido * 104729 % 1000000)::text, 6, '0'),
                              null, null, null, null, 'El cliente indicó que ya pagó con QR');
end $$;
