-- =====================================================================
-- 09 · Ciclo IV · P5 Reportes y calidad del servicio (CU23 a CU26)
-- ---------------------------------------------------------------------
-- Solo agrega tablas, permisos y funciones: no cambia datos existentes.
-- Se puede ejecutar más de una vez.
--
--   php Bakend/database/ejecutar.php 09_reportes_calificaciones.sql
-- =====================================================================

set search_path = mitsu, extensions, public;

-- Consulta personalizada que un usuario guarda para volver a usarla (CU23, CU24)
create table if not exists reporte_guardado (
    id         serial primary key,
    usuario_id integer      not null references usuario (id),
    nombre     varchar(80)  not null check (length(trim(nombre)) >= 3),
    tipo       varchar(30)  not null check (tipo ~ '^[a-z_]+$'),
    parametros jsonb        not null default '{}'::jsonb,
    creado_en  timestamptz  not null default now()
);
create unique index if not exists ux_reporte_guardado_nombre on reporte_guardado (usuario_id, normalizar(nombre));

-- Calificación de un pedido entregado o de una clase a la que se asistió (CU26)
create table if not exists calificacion (
    id             serial primary key,
    usuario_id     integer      not null references usuario (id),
    tipo           varchar(10)  not null check (tipo in ('PEDIDO', 'CLASE')),
    pedido_id      integer      unique references pedido (id),
    reserva_id     integer      unique references reserva (id),
    puntaje        smallint     not null check (puntaje between 1 and 5),
    comentario     varchar(400),
    respuesta      varchar(400),
    respondido_por integer      references usuario (id),
    respondido_en  timestamptz,
    creado_en      timestamptz  not null default now(),
    check ((tipo = 'PEDIDO' and pedido_id is not null and reserva_id is null)
        or (tipo = 'CLASE' and reserva_id is not null and pedido_id is null)),
    check (respuesta is null or (respondido_por is not null and respondido_en is not null))
);
create index if not exists ix_calificacion_fecha on calificacion (creado_en desc);

-- Solo se califica lo propio y cuando el servicio ya se recibió
create or replace function mitsu.fn_validar_calificacion()
returns trigger
language plpgsql
set search_path = mitsu, public
as $$
begin
    if new.tipo = 'PEDIDO' and not exists (
        select 1 from pedido p where p.id = new.pedido_id and p.usuario_id = new.usuario_id and p.estado = 'ENTREGADO') then
        raise exception 'Solo puede calificar sus pedidos entregados.';
    end if;
    if new.tipo = 'CLASE' and not exists (
        select 1 from reserva r join alumno a on a.id = r.alumno_id
         where r.id = new.reserva_id and a.usuario_id = new.usuario_id and r.estado = 'ASISTIO') then
        raise exception 'Solo puede calificar las clases a las que asistió.';
    end if;
    return new;
end $$;

drop trigger if exists tr_calificacion_validar on calificacion;
create trigger tr_calificacion_validar before insert on calificacion
    for each row execute function mitsu.fn_validar_calificacion();

-- Permisos del ciclo IV
insert into permiso (codigo, descripcion, modulo) values
    ('reportes.ver',             'Consultar y exportar reportes personalizados', 'P5 Reportes'),
    ('calificaciones.gestionar', 'Ver y responder calificaciones del servicio',  'P5 Reportes')
on conflict (codigo) do nothing;

insert into rolpermiso (rol_id, permiso_id)
select r.id, p.id from rol r cross join permiso p
 where r.nombre in ('ADMINISTRADOR', 'ADMINISTRATIVO')
   and p.codigo in ('reportes.ver', 'calificaciones.gestionar')
on conflict do nothing;

-- Usuario de la aplicación y seguridad a nivel de fila
grant select, insert, update, delete on reporte_guardado to mitsu_app;
grant select, insert, update on calificacion to mitsu_app;
revoke delete on calificacion from mitsu_app;
grant usage, select on sequence reporte_guardado_id_seq, calificacion_id_seq to mitsu_app;
grant execute on function mitsu.fn_validar_calificacion() to mitsu_app;

alter table reporte_guardado enable row level security;
drop policy if exists app_acceso on reporte_guardado;
create policy app_acceso on reporte_guardado for all to mitsu_app using (true) with check (true);
alter table calificacion enable row level security;
drop policy if exists app_acceso on calificacion;
create policy app_acceso on calificacion for all to mitsu_app using (true) with check (true);
