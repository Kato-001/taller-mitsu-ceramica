-- =====================================================================
-- Taller de Cerámica Mitsü
-- 05_consultas.sql · 30 consultas: 10 simples, 10 múltiples (JOIN) y
-- 10 subconsultas, más ejemplos de actualización de tuplas.
-- Cada consulta empieza con "-- Qnn ·" para que el informe la ubique.
-- =====================================================================

set search_path = mitsu, extensions, public;

-- ---------------------------------------------------------------------
-- Actualización de tuplas (se ejecutan en una transacción que se revierte)
-- ---------------------------------------------------------------------
-- I1 · Insertar un proveedor nuevo
insert into proveedor (nombre, nit, contacto, telefono, ciudad)
values ('Arcillas del Chaco (demo)', '4056789014', 'Rosa Ayala', '46640001', 'Tarija') returning id, nombre, ciudad;
-- U1 · Subir 5 % el precio de las tazas (queda en historial_precio por trigger)
update pieza set precio = round(precio * 1.05, 2)
 where categoria_id = (select id from categoria where nombre = 'Tazas y vasos') returning codigo, nombre, precio;
-- D1 · Borrar sesiones PHP vencidas (la única tabla donde se borra: no tiene historial)
delete from sesion where actualizado < now() - interval '2 hours' returning id;

-- ---------------------------------------------------------------------
-- CONSULTAS SIMPLES (una tabla)
-- ---------------------------------------------------------------------
-- Q01 · Talleres activos ordenados por precio mensual
select nombre, nivel, publico, precio_clase, precio_mensual from taller where estado = 'ACTIVO' order by precio_mensual desc;
-- Q02 · Materiales en o bajo el stock mínimo (alerta de compra)
select nombre, stock, stock_minimo from material where stock <= stock_minimo and estado = 'ACTIVO' order by stock;
-- Q03 · Piezas a la venta con menos de 5 unidades
select codigo, nombre, precio, stock from pieza where estado = 'ACTIVO' and stock < 5 order by stock, nombre;
-- Q04 · Clases programadas para hoy
select fecha, hora_inicio, hora_fin, cupo, estado from sesion_clase where fecha = hoy_bo() order by hora_inicio;
-- Q05 · Proveedores de Cochabamba o La Paz
select nombre, ciudad, telefono from proveedor where ciudad in ('Cochabamba', 'La Paz') order by ciudad;
-- Q06 · Ventas anuladas con su motivo
select numero, fecha::date as fecha, total, motivo_anulacion from venta where estado = 'ANULADA' order by fecha desc;
-- Q07 · Cantidad de usuarios por estado
select estado, count(*) as usuarios from usuario group by estado;
-- Q08 · Compras registradas en los últimos 30 días
select numero, fecha, total from compra where estado = 'REGISTRADA' and fecha >= hoy_bo() - 30 order by fecha desc;
-- Q09 · Intentos de inicio de sesión fallidos de la última semana
select ip, count(*) as intentos from bitacora where accion = 'LOGIN_FALLIDO' and fecha > now() - interval '7 days' group by ip;
-- Q10 · Pedidos web por estado
select estado, count(*) as pedidos, coalesce(sum(total), 0) as monto from pedido group by estado order by estado;

-- ---------------------------------------------------------------------
-- CONSULTAS MÚLTIPLES (JOIN de varias tablas)
-- ---------------------------------------------------------------------
-- Q11 · Horario semanal: taller, maestra, día y hora
select t.nombre as taller, u.nombre || ' ' || u.apellido as maestra, h.dia_semana, h.hora_inicio, h.hora_fin, h.cupo
  from horario h join taller t on t.id = h.taller_id join maestra m on m.id = h.maestra_id join usuario u on u.id = m.usuario_id
 where h.estado = 'ACTIVO' order by h.dia_semana, h.hora_inicio;
-- Q12 · Ocupación de las clases de esta semana
select s.fecha, t.nombre as taller, count(r.id) filter (where r.estado <> 'CANCELADA') as reservas, s.cupo
  from sesion_clase s join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id
  left join reserva r on r.sesion_id = s.id
 where s.fecha between date_trunc('week', hoy_bo())::date and date_trunc('week', hoy_bo())::date + 6
 group by s.id, s.fecha, t.nombre, s.cupo order by s.fecha, t.nombre;
-- Q13 · Porcentaje de asistencia por alumno
select u.nombre || ' ' || u.apellido as alumno,
       count(*) filter (where r.estado = 'ASISTIO') as asistio, count(*) filter (where r.estado = 'FALTO') as falto,
       round(100.0 * count(*) filter (where r.estado = 'ASISTIO') / nullif(count(*) filter (where r.estado in ('ASISTIO', 'FALTO')), 0)) as porcentaje
  from reserva r join alumno a on a.id = r.alumno_id join usuario u on u.id = a.usuario_id
 group by alumno order by porcentaje desc nulls last;
-- Q14 · Detalle de compras con proveedor y material
select c.numero, p.nombre as proveedor, m.nombre as material, d.cantidad, d.costo_unitario, d.subtotal
  from compra c join proveedor p on p.id = c.proveedor_id join detalle_compra d on d.compra_id = c.id join material m on m.id = d.material_id
 order by c.fecha desc, m.nombre;
-- Q15 · Ventas por método de pago (solo válidas)
select mp.nombre as metodo, count(v.id) as ventas, sum(v.total) as total
  from venta v join metodo_pago mp on mp.id = v.metodo_pago_id where v.estado = 'VALIDA' group by mp.nombre order by total desc;
-- Q16 · Piezas más vendidas en caja
select p.codigo, p.nombre, sum(d.cantidad) as unidades, sum(d.subtotal) as ingresos
  from detalle_venta d join venta v on v.id = d.venta_id join pieza p on p.id = d.pieza_id
 where v.estado = 'VALIDA' group by p.codigo, p.nombre order by unidades desc;
-- Q17 · Kardex de un material con el usuario que hizo cada movimiento
select mm.fecha::date as fecha, mm.tipo, mm.cantidad, mm.stock_resultante, u.nombre as usuario
  from movimiento_material mm join usuario u on u.id = mm.usuario_id join material m on m.id = mm.material_id
 where m.nombre = 'Arcilla roja' order by mm.fecha;
-- Q18 · Cierres de caja con su responsable y diferencia del arqueo
select c.abierta_en::date as dia, u.nombre || ' ' || u.apellido as responsable, c.total_efectivo, c.total_otros, c.diferencia
  from caja c join usuario u on u.id = c.usuario_id where c.estado = 'CERRADA' order by c.abierta_en desc;
-- Q19 · Permisos de cada rol
select r.nombre as rol, count(rp.permiso_id) as permisos, string_agg(p.codigo, ', ' order by p.codigo) as codigos
  from rol r left join rolpermiso rp on rp.rol_id = r.id left join permiso p on p.id = rp.permiso_id group by r.nombre order by permisos desc;
-- Q20 · Pedidos web con cliente y método con que se pagaron
select pe.numero, c.nombre as cliente, pe.total, pe.estado, pg.metodo, pg.estado as estado_pago
  from pedido pe join cliente c on c.id = pe.cliente_id left join pago pg on pg.pedido_id = pe.id
 order by pe.fecha desc;

-- ---------------------------------------------------------------------
-- SUBCONSULTAS
-- ---------------------------------------------------------------------
-- Q21 · Piezas con precio mayor al promedio de su categoría
select p.codigo, p.nombre, p.precio from pieza p
 where p.precio > (select avg(x.precio) from pieza x where x.categoria_id = p.categoria_id) order by p.precio desc;
-- Q22 · Alumnos que nunca faltaron a una clase
select u.nombre || ' ' || u.apellido as alumno from alumno a join usuario u on u.id = a.usuario_id
 where exists (select 1 from reserva r where r.alumno_id = a.id and r.estado = 'ASISTIO')
   and not exists (select 1 from reserva r where r.alumno_id = a.id and r.estado = 'FALTO');
-- Q23 · Talleres con más reservas que el promedio
select t.nombre, (select count(*) from reserva r join sesion_clase s on s.id = r.sesion_id join horario h on h.id = s.horario_id
                   where h.taller_id = t.id and r.estado <> 'CANCELADA') as reservas
  from taller t
 where (select count(*) from reserva r join sesion_clase s on s.id = r.sesion_id join horario h on h.id = s.horario_id
         where h.taller_id = t.id and r.estado <> 'CANCELADA')
     > (select avg(n) from (select count(*) as n from reserva r join sesion_clase s on s.id = r.sesion_id join horario h on h.id = s.horario_id
                             where r.estado <> 'CANCELADA' group by h.taller_id) x)
 order by reservas desc;
-- Q24 · Materiales que nunca se compraron
select nombre from material m where not exists (select 1 from detalle_compra d where d.material_id = m.id);
-- Q25 · Proveedor al que más se le compró
select nombre, (select sum(total) from compra c where c.proveedor_id = p.id and c.estado = 'REGISTRADA') as comprado from proveedor p
 where p.id = (select proveedor_id from compra where estado = 'REGISTRADA' group by proveedor_id order by sum(total) desc limit 1);
-- Q26 · Día con mayor venta del último mes
select fecha::date as dia, sum(total) as total from venta where estado = 'VALIDA' group by fecha::date
having sum(total) = (select max(t) from (select sum(total) as t from venta where estado = 'VALIDA' and fecha > now() - interval '30 days' group by fecha::date) x);
-- Q27 · Clases futuras que todavía tienen cupo libre
select s.fecha, s.hora_inicio, (select t.nombre from horario h join taller t on t.id = h.taller_id where h.id = s.horario_id) as taller,
       s.cupo - (select count(*) from reserva r where r.sesion_id = s.id and r.estado <> 'CANCELADA') as libres
  from sesion_clase s where s.fecha > hoy_bo() and s.estado = 'PROGRAMADA'
   and (select count(*) from reserva r where r.sesion_id = s.id and r.estado <> 'CANCELADA') < s.cupo
 order by s.fecha, s.hora_inicio limit 10;
-- Q28 · Maestras con más alumnos atendidos que la maestra promedio
select u.nombre || ' ' || u.apellido as maestra,
       (select count(*) from reserva r join sesion_clase s on s.id = r.sesion_id where s.maestra_id = m.id and r.estado = 'ASISTIO') as atendidos
  from maestra m join usuario u on u.id = m.usuario_id
 where (select count(*) from reserva r join sesion_clase s on s.id = r.sesion_id where s.maestra_id = m.id and r.estado = 'ASISTIO')
     >= (select avg(n) from (select count(*) as n from reserva r join sesion_clase s on s.id = r.sesion_id where r.estado = 'ASISTIO' group by s.maestra_id) x)
 order by atendidos desc;
-- Q29 · Clientes que compraron en caja y también en la tienda en línea
select nombre from cliente c
 where c.id in (select cliente_id from venta where estado = 'VALIDA') and c.id in (select cliente_id from pedido where estado in ('PAGADO', 'ENTREGADO'));
-- Q30 · Valor del inventario de materiales frente al total comprado en el mes
select (select sum(stock * costo_promedio) from material) as valor_inventario,
       (select coalesce(sum(total), 0) from compra where estado = 'REGISTRADA' and date_trunc('month', fecha) = date_trunc('month', hoy_bo())) as comprado_mes;
