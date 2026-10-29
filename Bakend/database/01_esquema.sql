-- =====================================================================
-- Taller de Cerámica Mitsü · Sistema de Información
-- 01_esquema.sql · Estructura de la base de datos (PostgreSQL 17)
-- ---------------------------------------------------------------------
-- Esquema propio "mitsu" (no se usa "public").
-- Normalizado hasta 3FN. Ningún registro con historial se borra:
-- se desactiva (estado ACTIVO / INACTIVO) o se anula con motivo.
--
--   Ciclo I   · P1 Seguridad y configuración
--   Ciclo II  · P2 Clases y reservas
--   Ciclo III · P3 Inventario y compras · P4 Ventas y caja
-- =====================================================================

-- Extensiones: unaccent (comparar sin tildes), pgcrypto (claves de los datos
-- de prueba) y btree_gist (restricción EXCLUDE de horarios sin cruce).
-- Se crean solo si faltan, así el script también lo puede correr el dueño del esquema.
do $$
begin
    if not exists (select 1 from pg_namespace where nspname = 'extensions') then
        create schema extensions;
    end if;
    if not exists (select 1 from pg_extension where extname = 'unaccent') then
        create extension unaccent with schema extensions;
    end if;
    if not exists (select 1 from pg_extension where extname = 'pgcrypto') then
        create extension pgcrypto with schema extensions;
    end if;
    if not exists (select 1 from pg_extension where extname = 'btree_gist') then
        create extension btree_gist with schema extensions;
    end if;
    if not exists (select 1 from pg_namespace where nspname = 'mitsu') then
        create schema mitsu;
    end if;
end $$;

set search_path = mitsu, extensions, public;

-- Nombre normalizado: sin mayúsculas ni tildes ("Cerámica" = "ceramica").
-- Se usa en índices únicos para impedir nombres repetidos.
create or replace function mitsu.normalizar(texto text)
returns text
language sql immutable parallel safe
as $$ select lower(extensions.unaccent('extensions.unaccent'::regdictionary, trim(coalesce(texto, '')))) $$;

-- Fecha y hora de Bolivia (los servidores trabajan en UTC)
create or replace function mitsu.hoy_bo()
returns date
language sql stable
as $$ select (now() at time zone 'America/La_Paz')::date $$;

-- =====================================================================
-- CICLO I · P1 SEGURIDAD Y CONFIGURACIÓN
-- =====================================================================

create table rol (
    id          smallserial primary key,
    nombre      varchar(40)  not null,
    descripcion varchar(200),
    estado      varchar(10)  not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO')),
    creado_en   timestamptz  not null default now()
);
create unique index ux_rol_nombre on rol (normalizar(nombre));

create table permiso (
    id          smallserial primary key,
    codigo      varchar(60)  not null unique check (codigo ~ '^[a-z_]+\.[a-z_]+$'),
    descripcion varchar(150) not null,
    modulo      varchar(40)  not null
);

create table rolpermiso (
    rol_id     smallint not null references rol (id),
    permiso_id smallint not null references permiso (id),
    primary key (rol_id, permiso_id)
);

create table usuario (
    id             serial primary key,
    rol_id         smallint     not null references rol (id),
    nombre         varchar(60)  not null check (length(trim(nombre)) >= 2),
    apellido       varchar(60)  not null check (length(trim(apellido)) >= 2),
    correo         varchar(120) not null check (correo ~* '^[^@\s]+@[^@\s]+\.[a-z]{2,}$'),
    telefono       varchar(20)  check (telefono ~ '^[0-9+\- ]{7,20}$'),
    clave_hash     varchar(255) not null,
    estado         varchar(10)  not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO')),
    ultimo_acceso  timestamptz,
    creado_en      timestamptz  not null default now(),
    actualizado_en timestamptz  not null default now()
);
create unique index ux_usuario_correo on usuario (lower(correo));
create index ix_usuario_rol on usuario (rol_id);
create index ix_usuario_nombre on usuario (normalizar(nombre || ' ' || apellido));

-- Sesiones PHP: en Vercel cada petición puede caer en otra instancia
create table sesion (
    id          varchar(128) primary key,
    datos       text         not null default '',
    actualizado timestamptz  not null default now()
);
create index ix_sesion_actualizado on sesion (actualizado);

create table bitacora (
    id         bigserial primary key,
    usuario_id integer      references usuario (id),
    accion     varchar(40)  not null,
    modulo     varchar(40)  not null,
    tabla      varchar(40),
    registro   bigint,
    detalle    varchar(500),
    ip         varchar(64),
    fecha      timestamptz  not null default now()
);
create index ix_bitacora_fecha on bitacora (fecha desc);
create index ix_bitacora_login on bitacora (ip, fecha) where accion = 'LOGIN_FALLIDO';

-- Recuperación de contraseña: código de 6 dígitos guardado con hash
create table recuperacion (
    id          serial primary key,
    usuario_id  integer     not null references usuario (id),
    codigo_hash varchar(255) not null,
    expira_en   timestamptz not null,
    intentos    smallint    not null default 0 check (intentos between 0 and 5),
    usado       boolean     not null default false,
    ip          varchar(64),
    creado_en   timestamptz not null default now(),
    check (expira_en > creado_en)
);
create index ix_recuperacion_usuario on recuperacion (usuario_id, creado_en desc);

create table configuracion (
    clave       varchar(40)  primary key check (clave ~ '^[A-Z_]+$'),
    valor       varchar(300) not null,
    descripcion varchar(200) not null
);

create table categoria (
    id     smallserial primary key,
    nombre varchar(60) not null,
    tipo   varchar(10) not null check (tipo in ('PIEZA', 'MATERIAL')),
    estado varchar(10) not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);
create unique index ux_categoria_nombre on categoria (tipo, normalizar(nombre));

create table unidad (
    id          smallserial primary key,
    nombre      varchar(30) not null,
    abreviatura varchar(10) not null,
    estado      varchar(10) not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);
create unique index ux_unidad_nombre on unidad (normalizar(nombre));

create table metodo_pago (
    id     smallserial primary key,
    nombre varchar(30) not null,
    estado varchar(10) not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);
create unique index ux_metodo_pago_nombre on metodo_pago (normalizar(nombre));

-- =====================================================================
-- CICLO II · P2 CLASES Y RESERVAS
-- =====================================================================

create table maestra (
    id           serial primary key,
    usuario_id   integer      not null unique references usuario (id),
    especialidad varchar(120) not null,
    biografia    varchar(500),
    estado       varchar(10)  not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);

create table alumno (
    id               serial primary key,
    usuario_id       integer     not null unique references usuario (id),
    ci               varchar(15) check (ci ~ '^[0-9]{5,12}(-[0-9A-Z]{1,3})?$'),
    fecha_nacimiento date        check (fecha_nacimiento between date '1920-01-01' and current_date),
    nivel            varchar(12) not null default 'INICIAL' check (nivel in ('INICIAL', 'INTERMEDIO', 'AVANZADO')),
    estado           varchar(10) not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);
create unique index ux_alumno_ci on alumno (ci) where ci is not null;

-- Taller = curso que ofrece el estudio (modelado, torno, niños…)
create table taller (
    id             smallserial primary key,
    nombre         varchar(80)   not null,
    descripcion    varchar(500)  not null,
    nivel          varchar(12)   not null check (nivel in ('INICIAL', 'INTERMEDIO', 'AVANZADO', 'TODOS')),
    publico        varchar(10)   not null default 'ADULTOS' check (publico in ('ADULTOS', 'NINOS', 'TODOS')),
    duracion_min   smallint      not null default 120 check (duracion_min between 30 and 300),
    precio_clase   numeric(10,2) not null check (precio_clase > 0),
    precio_mensual numeric(10,2) not null check (precio_mensual > 0),
    estado         varchar(10)   not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO')),
    creado_en      timestamptz   not null default now()
);
create unique index ux_taller_nombre on taller (normalizar(nombre));

-- Horario semanal fijo de un taller con su maestra
create table horario (
    id          serial primary key,
    taller_id   smallint not null references taller (id),
    maestra_id  integer  not null references maestra (id),
    dia_semana  smallint not null check (dia_semana between 1 and 7),   -- 1 = lunes (ISO)
    hora_inicio time     not null,
    hora_fin    time     not null,
    cupo        smallint not null check (cupo between 1 and 30),
    estado      varchar(10) not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO')),
    check (hora_fin > hora_inicio),
    -- Una maestra no puede tener dos horarios activos que se crucen el mismo día
    constraint horario_sin_cruce_maestra exclude using gist (
        maestra_id with =,
        dia_semana with =,
        tsrange(date '2000-01-01' + hora_inicio, date '2000-01-01' + hora_fin) with &&
    ) where (estado = 'ACTIVO')
);
create index ix_horario_taller on horario (taller_id);

-- Sesión = clase concreta de un horario en una fecha
create table sesion_clase (
    id                 serial primary key,
    horario_id         integer     not null references horario (id),
    maestra_id         integer     not null references maestra (id),   -- puede reemplazarse
    fecha              date        not null,
    hora_inicio        time        not null,
    hora_fin           time        not null,
    cupo               smallint    not null check (cupo between 1 and 30),
    estado             varchar(12) not null default 'PROGRAMADA' check (estado in ('PROGRAMADA', 'REALIZADA', 'CANCELADA')),
    motivo_cancelacion varchar(255),
    check (hora_fin > hora_inicio),
    check (estado <> 'CANCELADA' or motivo_cancelacion is not null),
    unique (horario_id, fecha)
);
create index ix_sesion_fecha on sesion_clase (fecha);

create table reserva (
    id                 serial primary key,
    sesion_id          integer     not null references sesion_clase (id),
    alumno_id          integer     not null references alumno (id),
    estado             varchar(10) not null default 'RESERVADA' check (estado in ('RESERVADA', 'CANCELADA', 'ASISTIO', 'FALTO')),
    registrado_por     integer     references usuario (id),
    creado_en          timestamptz not null default now(),
    cancelado_en       timestamptz,
    motivo_cancelacion varchar(255),
    asistencia_en      timestamptz,
    check (estado <> 'CANCELADA' or motivo_cancelacion is not null)
);
-- Un alumno no se inscribe dos veces a la misma clase
create unique index ux_reserva_activa on reserva (sesion_id, alumno_id) where estado <> 'CANCELADA';
create index ix_reserva_alumno on reserva (alumno_id);

-- =====================================================================
-- CICLO III · P3 INVENTARIO Y COMPRAS
-- =====================================================================

create table proveedor (
    id        smallserial primary key,
    nombre    varchar(100) not null,
    nit       varchar(20)  check (nit ~ '^[0-9]{5,15}$'),
    contacto  varchar(80),
    telefono  varchar(20)  check (telefono ~ '^[0-9+\- ]{7,20}$'),
    correo    varchar(120) check (correo ~* '^[^@\s]+@[^@\s]+\.[a-z]{2,}$'),
    ciudad    varchar(40)  not null,
    direccion varchar(200),
    estado    varchar(10)  not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);
create unique index ux_proveedor_nombre on proveedor (normalizar(nombre));
create unique index ux_proveedor_nit on proveedor (nit) where nit is not null;

-- Insumo para producir (arcilla, cuarzo, esmaltes, óxidos…)
create table material (
    id             serial primary key,
    categoria_id   smallint      not null references categoria (id),
    unidad_id      smallint      not null references unidad (id),
    nombre         varchar(80)   not null,
    descripcion    varchar(300),
    stock          numeric(12,2) not null default 0 check (stock >= 0),
    stock_minimo   numeric(12,2) not null default 0 check (stock_minimo >= 0),
    costo_promedio numeric(12,2) not null default 0 check (costo_promedio >= 0),
    estado         varchar(10)   not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO'))
);
create unique index ux_material_nombre on material (normalizar(nombre));

create sequence compra_numero_seq;
create table compra (
    id               serial primary key,
    numero           varchar(12)   not null unique default 'CP-' || lpad(nextval('mitsu.compra_numero_seq')::text, 6, '0'),
    proveedor_id     smallint      not null references proveedor (id),
    usuario_id       integer       not null references usuario (id),
    fecha            date          not null default mitsu.hoy_bo(),
    observacion      varchar(300),
    total            numeric(12,2) not null default 0 check (total >= 0),
    estado           varchar(10)   not null default 'REGISTRADA' check (estado in ('REGISTRADA', 'ANULADA')),
    motivo_anulacion varchar(255),
    creado_en        timestamptz   not null default now(),
    check (estado <> 'ANULADA' or motivo_anulacion is not null)
);
create index ix_compra_fecha on compra (fecha desc);

create table detalle_compra (
    id             serial primary key,
    compra_id      integer       not null references compra (id),
    material_id    integer       not null references material (id),
    cantidad       numeric(12,2) not null check (cantidad > 0),
    costo_unitario numeric(12,2) not null check (costo_unitario >= 0),
    subtotal       numeric(12,2) generated always as (round(cantidad * costo_unitario, 2)) stored,
    unique (compra_id, material_id)
);

-- Kardex de materiales: todo cambio de stock queda registrado aquí
create table movimiento_material (
    id               bigserial primary key,
    material_id      integer       not null references material (id),
    tipo             varchar(10)   not null check (tipo in ('ENTRADA', 'CONSUMO', 'MERMA', 'AJUSTE_MAS', 'AJUSTE_MENOS')),
    cantidad         numeric(12,2) not null check (cantidad > 0),
    stock_resultante numeric(12,2) not null default 0 check (stock_resultante >= 0),
    motivo           varchar(255)  not null,
    compra_id        integer       references compra (id),
    usuario_id       integer       not null references usuario (id),
    fecha            timestamptz   not null default now()
);
create index ix_movmat_material on movimiento_material (material_id, fecha desc);

-- =====================================================================
-- CICLO III · P4 VENTAS Y CAJA
-- =====================================================================

-- Pieza de cerámica terminada que se vende (tazas, vasijas, vajilla…)
create table pieza (
    id           serial primary key,
    codigo       varchar(12)   not null unique check (codigo ~ '^[A-Z]{2,4}-[0-9]{3,6}$'),
    categoria_id smallint      not null references categoria (id),
    nombre       varchar(100)  not null,
    descripcion  varchar(500),
    precio       numeric(10,2) not null check (precio > 0),
    stock        integer       not null default 0 check (stock >= 0),
    stock_minimo integer       not null default 1 check (stock_minimo >= 0),
    imagen       varchar(120),                       -- imagen estática de /assets
    estado       varchar(10)   not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO')),
    creado_en    timestamptz   not null default now()
);
create unique index ux_pieza_nombre on pieza (normalizar(nombre));

-- Foto subida desde el sistema (Vercel no guarda archivos en disco)
create table pieza_imagen (
    pieza_id       integer     primary key references pieza (id),
    tipo_mime      varchar(20) not null check (tipo_mime in ('image/jpeg', 'image/png', 'image/webp')),
    contenido      bytea       not null check (octet_length(contenido) <= 2097152),
    actualizado_en timestamptz not null default now()
);

create table historial_precio (
    id              bigserial primary key,
    pieza_id        integer       not null references pieza (id),
    precio_anterior numeric(10,2) not null,
    precio_nuevo    numeric(10,2) not null,
    usuario_id      integer       references usuario (id),
    fecha           timestamptz   not null default now()
);

create table cliente (
    id        serial primary key,
    nombre    varchar(120) not null,
    ci_nit    varchar(20)  check (ci_nit ~ '^[0-9]{4,15}(-[0-9A-Z]{1,3})?$'),
    telefono  varchar(20)  check (telefono ~ '^[0-9+\- ]{7,20}$'),
    correo    varchar(120) check (correo ~* '^[^@\s]+@[^@\s]+\.[a-z]{2,}$'),
    estado    varchar(10)  not null default 'ACTIVO' check (estado in ('ACTIVO', 'INACTIVO')),
    creado_en timestamptz  not null default now()
);
create unique index ux_cliente_ci_nit on cliente (ci_nit) where ci_nit is not null;
create index ix_cliente_nombre on cliente (normalizar(nombre));

-- Solo puede haber una caja abierta a la vez en el taller
create table caja (
    id              serial primary key,
    usuario_id      integer       not null references usuario (id),
    abierta_en      timestamptz   not null default now(),
    monto_inicial   numeric(10,2) not null check (monto_inicial >= 0),
    cerrada_en      timestamptz,
    cerrada_por     integer       references usuario (id),
    total_efectivo  numeric(12,2),
    total_otros     numeric(12,2),
    monto_declarado numeric(12,2) check (monto_declarado >= 0),
    diferencia      numeric(12,2),
    estado          varchar(10)   not null default 'ABIERTA' check (estado in ('ABIERTA', 'CERRADA')),
    observacion     varchar(300),
    check (estado = 'ABIERTA' or (cerrada_en is not null and monto_declarado is not null))
);
create unique index ux_caja_unica_abierta on caja (estado) where estado = 'ABIERTA';

create sequence venta_numero_seq;
create table venta (
    id               serial primary key,
    numero           varchar(12)   not null unique default 'NV-' || lpad(nextval('mitsu.venta_numero_seq')::text, 6, '0'),
    caja_id          integer       not null references caja (id),
    cliente_id       integer       not null references cliente (id),
    usuario_id       integer       not null references usuario (id),
    metodo_pago_id   smallint      not null references metodo_pago (id),
    fecha            timestamptz   not null default now(),
    total            numeric(12,2) not null default 0 check (total >= 0),
    monto_recibido   numeric(12,2) check (monto_recibido >= 0),
    estado           varchar(10)   not null default 'VALIDA' check (estado in ('VALIDA', 'ANULADA')),
    motivo_anulacion varchar(255),
    anulado_por      integer       references usuario (id),
    anulado_en       timestamptz,
    check (estado <> 'ANULADA' or (motivo_anulacion is not null and anulado_por is not null))
);
create index ix_venta_fecha on venta (fecha desc);
create index ix_venta_caja on venta (caja_id);

create table detalle_venta (
    id              serial primary key,
    venta_id        integer       not null references venta (id),
    pieza_id        integer       not null references pieza (id),
    cantidad        integer       not null check (cantidad > 0),
    precio_unitario numeric(10,2) not null check (precio_unitario > 0),   -- copiado al vender
    subtotal        numeric(12,2) generated always as (cantidad * precio_unitario) stored,
    unique (venta_id, pieza_id)
);

-- Kardex de piezas
create table movimiento_pieza (
    id               bigserial primary key,
    pieza_id         integer     not null references pieza (id),
    tipo             varchar(12) not null check (tipo in ('PRODUCCION', 'VENTA', 'ANULACION', 'AJUSTE_MAS', 'AJUSTE_MENOS')),
    cantidad         integer     not null check (cantidad > 0),
    stock_resultante integer     not null default 0 check (stock_resultante >= 0),
    motivo           varchar(255) not null,
    venta_id         integer     references venta (id),
    usuario_id       integer     not null references usuario (id),
    fecha            timestamptz not null default now()
);
create index ix_movpieza_pieza on movimiento_pieza (pieza_id, fecha desc);
