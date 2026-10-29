-- =====================================================================
-- Taller de Cerámica Mitsü
-- 03_datos_iniciales.sql · Catálogos, permisos y DATOS DE DEMOSTRACIÓN
-- ---------------------------------------------------------------------
-- Los horarios salen de la entrevista a la gerencia. Los precios, stocks,
-- proveedores, alumnos y ventas son ESTIMADOS / FICTICIOS para demostrar
-- el sistema. Las fechas se calculan desde HOY, así siempre hay datos
-- de "hoy", de días pasados y de días futuros.
--
-- Cuentas de prueba (contraseñas inventadas, solo para la demostración):
--   admin@mitsu.test    Admin2026!     Administrador (gerencia)
--   ventas@mitsu.test   Ventas2026!    Personal administrativo
--   maestra@mitsu.test  Maestra2026!   Maestra
--   alumno@mitsu.test   Alumno2026!    Alumno
-- =====================================================================

set search_path = mitsu, extensions, public;

-- ---------------------------------------------------------------------
-- Roles y permisos
-- ---------------------------------------------------------------------
insert into rol (nombre, descripcion) values
    ('ADMINISTRADOR',  'Gerencia del taller: acceso completo y configuración'),
    ('ADMINISTRATIVO', 'Atención al cliente, ventas, caja, inventario y reservas'),
    ('MAESTRA',        'Imparte talleres, ve sus clases y registra asistencia'),
    ('ALUMNO',         'Reserva clases desde su portal');

insert into permiso (codigo, descripcion, modulo) values
    ('panel.ver',              'Ver el panel de indicadores',                 'P1 Seguridad'),
    ('usuarios.gestionar',     'Registrar, editar y desactivar usuarios',     'P1 Seguridad'),
    ('roles.gestionar',        'Crear roles y asignar permisos',              'P1 Seguridad'),
    ('bitacora.ver',           'Consultar la bitácora del sistema',           'P1 Seguridad'),
    ('configuracion.gestionar','Editar datos del taller y catálogos',         'P1 Seguridad'),
    ('maestras.gestionar',     'Registrar y editar maestras',                 'P2 Clases'),
    ('talleres.gestionar',     'Registrar talleres, precios y horarios',      'P2 Clases'),
    ('sesiones.gestionar',     'Generar, cancelar y ajustar clases del calendario', 'P2 Clases'),
    ('alumnos.gestionar',      'Registrar y editar alumnos',                  'P2 Clases'),
    ('reservas.gestionar',     'Reservar o cancelar clases para un alumno',   'P2 Clases'),
    ('asistencia.registrar',   'Registrar asistencia de cualquier clase',     'P2 Clases'),
    ('clases.propias',         'Ver sus clases y registrar su asistencia',    'P2 Clases'),
    ('portal.alumno',          'Usar el portal del alumno',                   'P2 Clases'),
    ('materiales.gestionar',   'Registrar materiales y su consumo o merma',   'P3 Inventario'),
    ('proveedores.gestionar',  'Registrar proveedores',                       'P3 Inventario'),
    ('compras.gestionar',      'Registrar y anular compras',                  'P3 Inventario'),
    ('piezas.gestionar',       'Registrar piezas, fotos, precios y producción','P4 Ventas'),
    ('clientes.gestionar',     'Registrar clientes',                          'P4 Ventas'),
    ('ventas.registrar',       'Vender en caja y emitir nota de venta',       'P4 Ventas'),
    ('ventas.anular',          'Anular ventas con motivo',                    'P4 Ventas'),
    ('caja.gestionar',         'Abrir y cerrar caja',                         'P4 Ventas');

insert into rolpermiso (rol_id, permiso_id)
select r.id, p.id from rol r cross join permiso p
 where (r.nombre = 'ADMINISTRADOR' and p.codigo not in ('portal.alumno', 'clases.propias'))
    or (r.nombre = 'ADMINISTRATIVO' and p.codigo in ('panel.ver', 'alumnos.gestionar', 'reservas.gestionar',
            'asistencia.registrar', 'sesiones.gestionar', 'materiales.gestionar', 'proveedores.gestionar',
            'compras.gestionar', 'piezas.gestionar', 'clientes.gestionar', 'ventas.registrar', 'caja.gestionar'))
    or (r.nombre = 'MAESTRA' and p.codigo in ('panel.ver', 'clases.propias'))
    or (r.nombre = 'ALUMNO' and p.codigo = 'portal.alumno');

-- ---------------------------------------------------------------------
-- Configuración y catálogos
-- ---------------------------------------------------------------------
insert into configuracion (clave, valor, descripcion) values
    ('NOMBRE_TALLER',      'Taller de Cerámica Mitsü',            'Nombre que aparece en el sistema y en las notas de venta'),
    ('CIUDAD',             'Santa Cruz de la Sierra, Bolivia',    'Ciudad del taller'),
    ('TELEFONO',           '70000000',                            'Teléfono de contacto (dato de demostración)'),
    ('CORREO',             'contacto@mitsu.test',                 'Correo de contacto (dato de demostración)'),
    ('HORAS_CANCELACION',  '12',                                  'Horas mínimas de anticipación para que el alumno cancele una reserva'),
    ('SEMANAS_CALENDARIO', '4',                                   'Semanas hacia adelante que se generan clases en el calendario');

insert into unidad (nombre, abreviatura) values
    ('Kilogramo', 'kg'), ('Litro', 'L'), ('Unidad', 'u'), ('Gramo', 'g');

insert into metodo_pago (nombre) values ('Efectivo'), ('QR'), ('Transferencia');

insert into categoria (nombre, tipo) values
    ('Arcillas', 'MATERIAL'), ('Minerales', 'MATERIAL'), ('Esmaltes y engobes', 'MATERIAL'),
    ('Óxidos y pigmentos', 'MATERIAL'), ('Herramientas y consumibles', 'MATERIAL'),
    ('Tazas y vasos', 'PIEZA'), ('Vasijas y jarras', 'PIEZA'), ('Vajilla', 'PIEZA'), ('Decorativas', 'PIEZA');

-- ---------------------------------------------------------------------
-- Usuarios (contraseñas con bcrypt, igual que password_hash() de PHP)
-- ---------------------------------------------------------------------
insert into usuario (rol_id, nombre, apellido, correo, telefono, clave_hash)
select (select id from rol where nombre = u.rol), u.nombre, u.apellido, u.correo, u.telefono,
       crypt(u.clave, gen_salt('bf', 10))
from (values
    ('ADMINISTRADOR',  'Alessandra', 'Civardi',     'admin@mitsu.test',            '70010001', 'Admin2026!'),
    ('ADMINISTRATIVO', 'Valeria',    'Suárez',      'ventas@mitsu.test',           '70010002', 'Ventas2026!'),
    ('MAESTRA',        'Rosario',    'Gil',         'maestra@mitsu.test',          '70010003', 'Maestra2026!'),
    ('MAESTRA',        'Elena',      'Rojas',       'elena.rojas@mitsu.test',      '70010004', 'Maestra2026!'),
    ('MAESTRA',        'Lucía',      'Paz',         'lucia.paz@mitsu.test',        '70010005', 'Maestra2026!'),
    ('MAESTRA',        'Carla',      'Méndez',      'carla.mendez@mitsu.test',     '70010006', 'Maestra2026!'),
    ('ALUMNO',         'Camila',     'Sosa',        'alumno@mitsu.test',           '71020001', 'Alumno2026!'),
    ('ALUMNO',         'Diego',      'Fernández',   'diego.fernandez@mitsu.test',  '71020002', 'Alumno2026!'),
    ('ALUMNO',         'Rosa',       'Mamani',      'rosa.mamani@mitsu.test',      '71020003', 'Alumno2026!'),
    ('ALUMNO',         'Luis',       'Peña',        'luis.pena@mitsu.test',        '71020004', 'Alumno2026!'),
    ('ALUMNO',         'Andrea',     'Vaca',        'andrea.vaca@mitsu.test',      '71020005', 'Alumno2026!'),
    ('ALUMNO',         'Mateo',      'Justiniano',  'mateo.justiniano@mitsu.test', '71020006', 'Alumno2026!'),
    ('ALUMNO',         'Sofía',      'Arteaga',     'sofia.arteaga@mitsu.test',    '71020007', 'Alumno2026!'),
    ('ALUMNO',         'Gabriel',    'Roca',        'gabriel.roca@mitsu.test',     '71020008', 'Alumno2026!'),
    ('ALUMNO',         'Valentina',  'Cuéllar',     'valentina.cuellar@mitsu.test','71020009', 'Alumno2026!'),
    ('ALUMNO',         'Joaquín',    'Salvatierra', 'joaquin.salvatierra@mitsu.test','71020010','Alumno2026!')
) as u (rol, nombre, apellido, correo, telefono, clave);

insert into maestra (usuario_id, especialidad, biografia)
select u.id, m.especialidad, m.bio from usuario u join (values
    ('maestra@mitsu.test',        'Modelado a mano',             'Técnicas de pellizco, churro y placa.'),
    ('elena.rojas@mitsu.test',    'Modelado avanzado y esmaltes','Esmaltado de alta temperatura y diseño de superficies.'),
    ('lucia.paz@mitsu.test',      'Talleres y cerámica infantil','Talleres nocturnos y clases para niños.'),
    ('carla.mendez@mitsu.test',   'Torno alfarero',              'Centrado, levantado y retorneado en torno eléctrico.')
) as m (correo, especialidad, bio) on u.correo = m.correo;

insert into alumno (usuario_id, ci, fecha_nacimiento, nivel)
select u.id, a.ci, a.nac::date, a.nivel from usuario u join (values
    ('alumno@mitsu.test',             '8123451', '1998-04-12', 'INTERMEDIO'),
    ('diego.fernandez@mitsu.test',    '8123452', '1995-09-30', 'INICIAL'),
    ('rosa.mamani@mitsu.test',        '8123453', '2001-01-21', 'AVANZADO'),
    ('luis.pena@mitsu.test',          '8123454', '1990-06-02', 'INICIAL'),
    ('andrea.vaca@mitsu.test',        '8123455', '2003-11-17', 'INTERMEDIO'),
    ('mateo.justiniano@mitsu.test',   '8123456', '2015-03-08', 'INICIAL'),
    ('sofia.arteaga@mitsu.test',      '8123457', '2014-08-25', 'INICIAL'),
    ('gabriel.roca@mitsu.test',       '8123458', '1987-12-01', 'AVANZADO'),
    ('valentina.cuellar@mitsu.test',  '8123459', '1999-02-14', 'INTERMEDIO'),
    ('joaquin.salvatierra@mitsu.test','8123460', '1993-07-19', 'INICIAL')
) as a (correo, ci, nac, nivel) on u.correo = a.correo;

-- ---------------------------------------------------------------------
-- Talleres y horarios (horarios según la entrevista; precios estimados)
-- ---------------------------------------------------------------------
insert into taller (nombre, descripcion, nivel, publico, precio_clase, precio_mensual) values
    ('Modelado a mano',        'Pellizco, churro y placa para crear piezas utilitarias desde cero.',          'INICIAL',    'ADULTOS', 90,  320),
    ('Modelado avanzado',      'Piezas de mayor tamaño, tapas, asas y acabados con engobes.',                 'AVANZADO',   'ADULTOS', 110, 400),
    ('Torno alfarero',         'Centrado, levantado de paredes y retorneado en torno eléctrico.',             'INTERMEDIO', 'ADULTOS', 120, 420),
    ('Taller de modelado nocturno', 'Taller libre después del trabajo, con guía de la maestra.',              'TODOS',      'ADULTOS', 90,  320),
    ('Taller de sábado para adultos', 'Encuentro de fin de semana para crear y esmaltar piezas propias.',     'TODOS',      'ADULTOS', 100, 360),
    ('Cerámica para niños',    'Juego con arcilla para niños de 6 a 12 años: figuras, cuencos y texturas.',   'INICIAL',    'NINOS',   70,  250),
    ('Modelado y torno',       'Combina mano y torno para proyectos personales.',                             'INTERMEDIO', 'ADULTOS', 120, 420);

insert into horario (taller_id, maestra_id, dia_semana, hora_inicio, hora_fin, cupo)
select t.id, m.id, h.dia, h.ini::time, h.fin::time, h.cupo
from (values
    ('Modelado a mano',               'maestra@mitsu.test',      2, '10:00', '12:00', 8),
    ('Modelado avanzado',             'elena.rojas@mitsu.test',  2, '10:00', '12:00', 6),
    ('Taller de modelado nocturno',   'lucia.paz@mitsu.test',    2, '18:00', '20:00', 8),
    ('Modelado a mano',               'maestra@mitsu.test',      3, '10:00', '12:00', 8),
    ('Torno alfarero',                'carla.mendez@mitsu.test', 3, '10:00', '12:00', 6),
    ('Taller de modelado nocturno',   'lucia.paz@mitsu.test',    3, '18:00', '20:00', 8),
    ('Modelado a mano',               'maestra@mitsu.test',      4, '10:00', '12:00', 8),
    ('Torno alfarero',                'carla.mendez@mitsu.test', 4, '10:00', '12:00', 6),
    ('Modelado a mano',               'elena.rojas@mitsu.test',  5, '10:00', '12:00', 8),
    ('Torno alfarero',                'carla.mendez@mitsu.test', 5, '10:00', '12:00', 6),
    ('Taller de sábado para adultos', 'elena.rojas@mitsu.test',  6, '10:00', '12:00', 10),
    ('Cerámica para niños',           'lucia.paz@mitsu.test',    6, '16:00', '18:00', 10),
    ('Modelado y torno',              'carla.mendez@mitsu.test', 6, '18:00', '20:00', 6)
) as h (taller, correo, dia, ini, fin, cupo)
join taller t on t.nombre = h.taller
join usuario u on u.correo = h.correo
join maestra m on m.usuario_id = u.id;

-- Clases del calendario: desde hace 3 semanas hasta dentro de 3 semanas
insert into sesion_clase (horario_id, maestra_id, fecha, hora_inicio, hora_fin, cupo, estado)
select h.id, h.maestra_id, d::date, h.hora_inicio, h.hora_fin, h.cupo,
       case when d::date < hoy_bo() then 'REALIZADA' else 'PROGRAMADA' end
from horario h
cross join generate_series(hoy_bo() - 21, hoy_bo() + 21, interval '1 day') d
where extract(isodow from d) = h.dia_semana;

-- Una clase futura cancelada (para mostrar el caso)
update sesion_clase set estado = 'CANCELADA', motivo_cancelacion = 'Mantenimiento del horno (dato de demostración)'
 where id = (select id from sesion_clase where fecha > hoy_bo() + 7 order by fecha, hora_inicio limit 1);

-- Reservas: las clases pasadas ya tienen asistencia registrada. Este trigger impide
-- reservar fechas pasadas, por eso se desactiva solo mientras se cargan los datos históricos.
alter table reserva disable trigger tg_reserva_validar;

insert into reserva (sesion_id, alumno_id, estado, registrado_por, creado_en, asistencia_en)
select x.sesion_id, x.alumno_id,
       case when x.fecha >= hoy_bo() then 'RESERVADA'
            when (abs(hashtext(x.sesion_id || 'a' || x.alumno_id)) % 6) = 0 then 'FALTO'
            else 'ASISTIO' end,
       x.usuario_id,
       (x.fecha - 3)::timestamp + time '15:00',
       case when x.fecha < hoy_bo() then x.fecha::timestamp + x.hora_fin end
from (
    select s.id as sesion_id, a.id as alumno_id, s.fecha, s.hora_fin, a.usuario_id, s.cupo,
           row_number() over (partition by s.id order by abs(hashtext(s.id || '-' || a.id))) as n
    from sesion_clase s
    join horario h on h.id = s.horario_id
    join taller t on t.id = h.taller_id
    cross join alumno a
    where s.estado <> 'CANCELADA'
      and (t.publico = 'NINOS') = (a.fecha_nacimiento > date '2012-01-01')   -- los niños van al taller infantil
      and (abs(hashtext(a.id || '/' || s.id)) % 10) < 4
) x
where x.n < x.cupo;   -- siempre queda al menos un cupo libre

alter table reserva enable trigger tg_reserva_validar;

-- ---------------------------------------------------------------------
-- Inventario: proveedores, materiales y compras (datos estimados)
-- ---------------------------------------------------------------------
insert into proveedor (nombre, nit, contacto, telefono, correo, ciudad, direccion) values
    ('Minera Andina Cochabamba (demo)',      '1023456011', 'Jorge Quiroga', '44250001', 'ventas@andina.test',  'Cochabamba', 'Zona industrial, km 5'),
    ('Minerales Illimani S.R.L. (demo)',     '2034567012', 'Marta Choque',  '22450002', 'pedidos@illimani.test','La Paz',     'Av. Montes 100'),
    ('Insumos Cerámicos del Oriente (demo)', '3045678013', 'Pablo Rivero',  '33450003', 'info@oriente.test',   'Santa Cruz', '4to anillo, calle 7');

insert into material (categoria_id, unidad_id, nombre, descripcion, stock_minimo)
select c.id, u.id, m.nombre, m.descr, m.minimo
from (values
    ('Arcillas',                   'kg', 'Arcilla roja',          'Arcilla de baja temperatura para modelado', 40),
    ('Arcillas',                   'kg', 'Arcilla gres blanca',   'Gres de alta temperatura para torno',       40),
    ('Arcillas',                   'kg', 'Caolín',                'Arcilla blanca para pastas y esmaltes',     10),
    ('Minerales',                  'kg', 'Cuarzo molido',         'Sílice para esmaltes',                      10),
    ('Minerales',                  'kg', 'Feldespato potásico',   'Fundente para esmaltes',                    10),
    ('Esmaltes y engobes',         'L',  'Esmalte transparente',  'Esmalte brillante de alta temperatura',     5),
    ('Esmaltes y engobes',         'L',  'Esmalte celeste nube',  'Esmalte de efecto escurrido',               4),
    ('Esmaltes y engobes',         'L',  'Engobe blanco',         'Engobe para decorar en cuero',              3),
    ('Óxidos y pigmentos',         'kg', 'Óxido de cobalto',      'Pigmento azul',                             2),
    ('Óxidos y pigmentos',         'kg', 'Óxido de hierro',       'Pigmento rojizo',                           2),
    ('Herramientas y consumibles', 'u',  'Pinceles de cerámica',  'Pinceles de pelo suave',                    12)
) as m (categoria, unidad, nombre, descr, minimo)
join categoria c on c.nombre = m.categoria and c.tipo = 'MATERIAL'
join unidad u on u.abreviatura = m.unidad;

do $$
declare
    v_admin  integer := (select id from usuario where correo = 'admin@mitsu.test');
    v_ventas integer := (select id from usuario where correo = 'ventas@mitsu.test');
    v_compra integer;
    c record;
    d record;
begin
    for c in
        select * from (values
            (1, 'Minera Andina Cochabamba (demo)',      28, 'Pedido mensual de arcillas'),
            (2, 'Minerales Illimani S.R.L. (demo)',     25, 'Minerales para esmaltes'),
            (3, 'Insumos Cerámicos del Oriente (demo)', 18, 'Esmaltes y pinceles'),
            (4, 'Minera Andina Cochabamba (demo)',      6,  'Reposición de arcilla gres')
        ) as t (n, proveedor, dias, obs)
    loop
        insert into compra (proveedor_id, usuario_id, fecha, observacion, creado_en)
        values ((select id from proveedor where nombre = c.proveedor), v_ventas, hoy_bo() - c.dias, c.obs,
                (hoy_bo() - c.dias)::timestamp + time '10:30')
        returning id into v_compra;

        for d in
            select m.id, x.cant, x.costo from (values
                (1, 'Arcilla roja', 150, 6.50), (1, 'Arcilla gres blanca', 120, 9.00), (1, 'Caolín', 30, 8.00),
                (2, 'Cuarzo molido', 40, 7.00), (2, 'Feldespato potásico', 30, 7.50),
                (2, 'Óxido de cobalto', 3, 180.00), (2, 'Óxido de hierro', 4, 45.00),
                (3, 'Esmalte transparente', 12, 95.00), (3, 'Esmalte celeste nube', 6, 120.00),
                (3, 'Engobe blanco', 5, 60.00), (3, 'Pinceles de cerámica', 20, 15.00),
                (4, 'Arcilla gres blanca', 80, 9.20)
            ) as x (n, material, cant, costo)
            join material m on m.nombre = x.material
            where x.n = c.n
        loop
            insert into detalle_compra (compra_id, material_id, cantidad, costo_unitario) values (v_compra, d.id, d.cant, d.costo);
            insert into movimiento_material (material_id, tipo, cantidad, motivo, compra_id, usuario_id, fecha)
            values (d.id, 'ENTRADA', d.cant, 'Compra ' || (select numero from compra where id = v_compra), v_compra, v_ventas,
                    (hoy_bo() - c.dias)::timestamp + time '10:30');
            update material set costo_promedio = d.costo where id = d.id;
        end loop;
    end loop;

    -- Consumo en clases y mermas: algunos materiales quedan bajo el mínimo (alertas)
    insert into movimiento_material (material_id, tipo, cantidad, motivo, usuario_id, fecha)
    select m.id, x.tipo, x.cant, x.motivo, v_ventas, now() - make_interval(days => x.dias)
    from (values
        ('Arcilla roja',          'CONSUMO', 95,  'Consumo en clases de modelado', 10),
        ('Arcilla roja',          'CONSUMO', 30,  'Consumo en clases de modelado', 2),
        ('Arcilla gres blanca',   'CONSUMO', 110, 'Consumo en clases de torno',    9),
        ('Cuarzo molido',         'CONSUMO', 25,  'Preparación de esmaltes',       12),
        ('Esmalte transparente',  'CONSUMO', 9,   'Esmaltado de piezas del mes',   5),
        ('Óxido de cobalto',      'CONSUMO', 2,   'Decoración de vajilla',         4),
        ('Pinceles de cerámica',  'MERMA',   11,  'Pinceles dañados',              3)
    ) as x (material, tipo, cant, motivo, dias)
    join material m on m.nombre = x.material;
end $$;

-- ---------------------------------------------------------------------
-- Piezas a la venta (fotos reales del taller; precios estimados)
-- ---------------------------------------------------------------------
insert into pieza (codigo, categoria_id, nombre, descripcion, precio, stock_minimo, imagen)
select p.codigo, c.id, p.nombre, p.descr, p.precio, p.minimo, p.imagen
from (values
    ('TZ-001', 'Tazas y vasos',    'Taza esmaltada Nube',          'Taza con relieve tallado y esmalte celeste escurrido.',            95,  3, 'assets/img/piezas/taza-celeste.jpg'),
    ('TZ-002', 'Tazas y vasos',    'Vaso Mejillas',                'Vaso de gres con rostro, mejillas rojas y hoja verde.',            85,  3, 'assets/img/piezas/vaso-rostro.jpg'),
    ('VS-001', 'Vasijas y jarras', 'Vasija Inti',                  'Vasija de dos asas con rostro y sol pintado a mano.',              320, 1, 'assets/img/piezas/vasija-sol.jpg'),
    ('VS-002', 'Vasijas y jarras', 'Vasija Pajarito',              'Vasija con rostro, aros y pajarito esmaltado en el borde.',        290, 2, 'assets/img/piezas/vasija-pajarito.jpg'),
    ('JR-001', 'Vasijas y jarras', 'Jarra Andina',                 'Jarra con rostro y friso de felinos inspirado en culturas andinas.', 350, 1, 'assets/img/piezas/jarra-andina.jpg'),
    ('VJ-001', 'Vajilla',          'Set café: vaso y bandeja',     'Vaso esmaltado con bandeja ovalada, el mismo de la cafetería.',    160, 2, 'assets/img/piezas/set-cafe.jpg'),
    ('VJ-002', 'Vajilla',          'Plato llano de gres 28 cm',    'Plato utilitario apto para microondas.',                           120, 4, null),
    ('VJ-003', 'Vajilla',          'Bowl esmaltado mediano',       'Bowl de 15 cm para sopas y ensaladas.',                            90,  3, null)
) as p (codigo, categoria, nombre, descr, precio, minimo, imagen)
join categoria c on c.nombre = p.categoria and c.tipo = 'PIEZA';

-- Producción inicial (hace 30 días)
insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, usuario_id, fecha)
select p.id, 'PRODUCCION', x.cant, 'Lote de producción inicial',
       (select id from usuario where correo = 'admin@mitsu.test'), now() - interval '30 days'
from (values ('TZ-001', 20), ('TZ-002', 22), ('VS-001', 3), ('VS-002', 2), ('JR-001', 5),
             ('VJ-001', 12), ('VJ-002', 20), ('VJ-003', 2)) as x (codigo, cant)
join pieza p on p.codigo = x.codigo;

insert into cliente (nombre, ci_nit, telefono, correo) values
    ('Cliente general',      null,        null,       null),
    ('María José Antelo',    '6543210',   '72030001', 'mj.antelo@correo.test'),
    ('Café del Parque (demo)','1029384756','33470002', 'compras@cafeparque.test'),
    ('Fernando Rivero',      '7788990',   '72030003', null),
    ('Paola Céspedes',       '5566778',   '72030004', 'paola.c@correo.test'),
    ('Hotel Los Tajibos (demo)', '1234509876', '33420005', null);

-- ---------------------------------------------------------------------
-- Ventas de los últimos 20 días: una caja por día; la de hoy queda abierta
-- ---------------------------------------------------------------------
do $$
declare
    v_ventas  integer := (select id from usuario where correo = 'ventas@mitsu.test');
    v_admin   integer := (select id from usuario where correo = 'admin@mitsu.test');
    v_caja    integer;
    v_venta   integer;
    v_dia     date;
    v_ventas_dia integer;
    v_pieza   record;
    v_cant    integer;
    i         integer;
    j         integer;
begin
    for v_dia in select generate_series(hoy_bo() - 20, hoy_bo(), interval '1 day')::date loop
        continue when extract(isodow from v_dia) in (1, 7);     -- lunes y domingo cerrado

        insert into caja (usuario_id, abierta_en, monto_inicial)
        values (v_ventas, v_dia + time '09:00', 200) returning id into v_caja;

        v_ventas_dia := 1 + abs(hashtext(v_dia::text)) % 3;
        for i in 1 .. v_ventas_dia loop
            insert into venta (caja_id, cliente_id, usuario_id, metodo_pago_id, fecha)
            values (v_caja,
                    (select id from cliente order by abs(hashtext(id || v_dia::text || i)) limit 1),
                    v_ventas,
                    1 + abs(hashtext(v_dia::text || i)) % 3,
                    v_dia + time '10:00' + make_interval(hours => i * 2))
            returning id into v_venta;

            j := 0;
            for v_pieza in
                select id, codigo from pieza
                 where codigo in ('TZ-001', 'TZ-002', 'VJ-001', 'VJ-002', 'JR-001') and stock > 3
                 order by abs(hashtext(id || '/' || v_venta)) limit 1 + abs(hashtext(v_venta::text)) % 2
            loop
                v_cant := case when v_pieza.codigo = 'JR-001' then 1 else 1 + abs(hashtext(v_pieza.id || v_dia::text)) % 2 end;
                insert into detalle_venta (venta_id, pieza_id, cantidad, precio_unitario) values (v_venta, v_pieza.id, v_cant, 1);
                insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, venta_id, usuario_id, fecha)
                values (v_pieza.id, 'VENTA', v_cant, 'Venta ' || (select numero from venta where id = v_venta), v_venta, v_ventas,
                        v_dia + time '10:00' + make_interval(hours => i * 2));
                j := j + 1;
            end loop;

            update venta set monto_recibido = ceil(total / 10) * 10 where id = v_venta;
        end loop;

        if v_dia < hoy_bo() then
            update caja c
               set total_efectivo  = coalesce((select sum(total) from venta where caja_id = c.id and estado = 'VALIDA' and metodo_pago_id = 1), 0),
                   total_otros     = coalesce((select sum(total) from venta where caja_id = c.id and estado = 'VALIDA' and metodo_pago_id <> 1), 0),
                   monto_declarado = c.monto_inicial + coalesce((select sum(total) from venta where caja_id = c.id and estado = 'VALIDA' and metodo_pago_id = 1), 0),
                   diferencia      = 0,
                   cerrada_en      = v_dia + time '20:30',
                   cerrada_por     = v_ventas,
                   estado          = 'CERRADA'
             where id = v_caja;
        end if;
    end loop;

    -- Una venta anulada con motivo (devuelve el stock)
    select id into v_venta from venta where fecha::date = hoy_bo() - 3 order by id limit 1;
    if v_venta is null then
        select id into v_venta from venta where fecha::date < hoy_bo() order by id desc limit 1;
    end if;
    insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, venta_id, usuario_id, fecha)
    select pieza_id, 'ANULACION', cantidad, 'Anulación de venta (demostración)', venta_id, v_admin, now() - interval '3 days'
      from detalle_venta where venta_id = v_venta;
    update venta set estado = 'ANULADA', motivo_anulacion = 'Pieza con fisura detectada al entregar (demostración)',
                     anulado_por = v_admin
     where id = v_venta;
end $$;

-- Un cambio de precio para el historial
select set_config('mitsu.usuario_id', (select id::text from usuario where correo = 'admin@mitsu.test'), false);
update pieza set precio = 95 where codigo = 'TZ-002' and precio = 85;
select set_config('mitsu.usuario_id', '', false);

-- Bitácora inicial
insert into bitacora (usuario_id, accion, modulo, detalle, ip, fecha)
select id, 'INICIAR_SESION', 'SEGURIDAD', 'Inicio de sesión (dato de demostración)', '127.0.0.1', now() - interval '1 day'
  from usuario where correo in ('admin@mitsu.test', 'ventas@mitsu.test', 'maestra@mitsu.test');
