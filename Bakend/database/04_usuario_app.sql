-- =====================================================================
-- Taller de Cerámica Mitsü
-- 04_usuario_app.sql · Usuario de base de datos de MÍNIMO PRIVILEGIO
-- ---------------------------------------------------------------------
-- La aplicación PHP NO se conecta como "postgres": usa "mitsu_app", que
-- solo puede leer/escribir datos y ejecutar procedimientos del esquema
-- mitsu. No puede crear, alterar ni borrar tablas.
--
-- La contraseña NO se guarda en este archivo ni en GitHub. Se ejecuta con:
--   php Bakend/database/ejecutar.php 04_usuario_app.sql  (toma APP_DB_PASSWORD del .env)
-- y la misma clave va en DATABASE_URL (.env local y variables de Vercel).
-- =====================================================================

-- Crear el rol y fijar su clave requiere privilegio CREATEROLE (usuario postgres).
-- Si este script lo corre el dueño del esquema, esa parte se omite.
do $$
declare
    puede_crear boolean := (select rolcreaterole or rolsuper from pg_roles where rolname = current_user);
begin
    if puede_crear then
        if not exists (select 1 from pg_roles where rolname = 'mitsu_app') then
            create role mitsu_app login noinherit;
        end if;
        execute format('alter role mitsu_app with password %L', :'clave_app');
        -- Zona horaria de Bolivia y esquema por defecto para cada conexión
        alter role mitsu_app set timezone = 'America/La_Paz';
        alter role mitsu_app set search_path = mitsu, extensions, public;
        alter role mitsu_app set statement_timeout = '15s';
    end if;
end $$;

grant usage on schema mitsu to mitsu_app;
grant select, insert, update, delete on all tables in schema mitsu to mitsu_app;
grant usage, select on all sequences in schema mitsu to mitsu_app;
grant execute on all functions in schema mitsu to mitsu_app;
grant execute on all procedures in schema mitsu to mitsu_app;

-- Las tablas que no deben cambiar desde la aplicación quedan de solo lectura
revoke insert, update, delete on permiso, bitacora, historial_precio,
       movimiento_material, movimiento_pieza from mitsu_app;
grant insert on bitacora, movimiento_material, movimiento_pieza, historial_precio to mitsu_app;

-- ---------------------------------------------------------------------
-- RLS (seguridad a nivel de fila) activada en todo el esquema.
-- Las claves públicas de Supabase (anon / authenticated) no tienen
-- permisos ni políticas: no pueden ver nada de este esquema.
-- ---------------------------------------------------------------------
do $$
declare t record;
begin
    for t in select tablename from pg_tables where schemaname = 'mitsu' loop
        execute format('alter table mitsu.%I enable row level security', t.tablename);
        execute format('drop policy if exists app_acceso on mitsu.%I', t.tablename);
        execute format('create policy app_acceso on mitsu.%I for all to mitsu_app using (true) with check (true)', t.tablename);
    end loop;
end $$;

revoke all on schema mitsu from public;
