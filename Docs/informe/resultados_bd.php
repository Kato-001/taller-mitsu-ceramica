<?php
/**
 * Ejecuta las consultas de 05_consultas.sql, los procedimientos almacenados y las
 * reglas (triggers) contra la base real con el usuario de la aplicación, dentro de
 * transacciones que se revierten, y guarda los resultados en datos/bd.json para el informe.
 *
 *   php Docs/informe/resultados_bd.php
 */

declare(strict_types=1);

$raiz = dirname(__DIR__, 2);
foreach (file("$raiz/.env", FILE_IGNORE_NEW_LINES) as $l) {
    if (str_starts_with($l, 'DATABASE_URL=')) {
        $u = parse_url(substr($l, 13));
    }
}
$pdo = new PDO("pgsql:host={$u['host']};port={$u['port']};dbname=postgres;sslmode=require", urldecode($u['user']), urldecode($u['pass']),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

function ejecutar(PDO $pdo, string $sql, int $max = 6): array
{
    $pdo->beginTransaction();
    try {
        $pdo->exec("select set_config('mitsu.usuario_id', '1', true)");
        $st = $pdo->query($sql);
        $filas = $st->columnCount() ? $st->fetchAll() : [];
        $r = ['ok' => true, 'total' => count($filas), 'filas' => array_slice($filas, 0, $max), 'columnas' => $filas ? array_keys($filas[0]) : []];
    } catch (PDOException $e) {
        $r = ['ok' => false, 'error' => trim(preg_replace('/^SQLSTATE\[\w+\]: [^:]+: \d+ ERROR:\s*/', '', strtok($e->getMessage(), "\n")))];
    }
    $pdo->rollBack();
    return $r;
}

// 1) Consultas del script
$sql = file_get_contents("$raiz/Bakend/database/05_consultas.sql");
preg_match_all('/^-- ((?:Q\d{2}|[IUD]\d)) · (.+)$\n((?:(?!^-- ).*\n?)+)/m', $sql, $m, PREG_SET_ORDER);
$consultas = [];
foreach ($m as [, $id, $titulo, $cuerpo]) {
    $cuerpo = trim($cuerpo);
    $consultas[] = ['id' => $id, 'titulo' => trim($titulo), 'sql' => $cuerpo] + ejecutar($pdo, $cuerpo);
}

// 2) Procedimientos almacenados (se revierten)
$pas = [
    ['PA1', 'fn_registrar_venta', 'Venta de 1 Vaso Mejillas en efectivo con Bs 200 recibidos',
     "select mitsu.fn_registrar_venta(2, 1, 1, 200, jsonb_build_array(jsonb_build_object('pieza_id', (select id from mitsu.pieza where codigo = 'TZ-002'), 'cantidad', 1))) as venta_id;
      select numero, total, monto_recibido, (select stock from mitsu.pieza where codigo = 'TZ-002') as stock_restante from mitsu.venta order by id desc limit 1"],
    ['PA2', 'fn_anular_venta', 'Anular la última venta válida con motivo',
     "select mitsu.fn_anular_venta((select max(id) from mitsu.venta where estado = 'VALIDA'), 1, 'Cliente devolvió la pieza');
      select numero, estado, motivo_anulacion from mitsu.venta where anulado_en is not null order by anulado_en desc limit 1"],
    ['PA3', 'fn_registrar_compra', 'Compra de 50 kg de arcilla roja a Bs 6,80',
     "select mitsu.fn_registrar_compra(2, 1, mitsu.hoy_bo(), 'Prueba', jsonb_build_array(jsonb_build_object('material_id', (select id from mitsu.material where nombre = 'Arcilla roja'), 'cantidad', 50, 'costo', 6.8))) as compra_id;
      select nombre, stock, costo_promedio from mitsu.material where nombre = 'Arcilla roja'"],
    ['PA4', 'fn_anular_compra', 'Anular la última compra registrada',
     "select mitsu.fn_anular_compra((select max(id) from mitsu.compra where estado = 'REGISTRADA'), 1, 'Proveedor envió material equivocado');
      select numero, estado, motivo_anulacion from mitsu.compra where estado = 'ANULADA' order by id desc limit 1"],
    ['PA5', 'fn_reservar_clase', 'Reservar la próxima clase con cupo para un alumno',
     "select mitsu.fn_reservar_clase((select s.id from mitsu.sesion_clase s where s.fecha > mitsu.hoy_bo() and s.estado = 'PROGRAMADA'
               and not exists (select 1 from mitsu.reserva r where r.sesion_id = s.id and r.alumno_id = 4) order by s.fecha limit 1), 4, 1) as reserva_id"],
    ['PA6', 'fn_generar_sesiones', 'Generar el calendario de las próximas 6 semanas',
     "select mitsu.fn_generar_sesiones(mitsu.hoy_bo(), mitsu.hoy_bo() + 42) as clases_creadas"],
    ['PA7', 'sp_cerrar_caja', 'Cerrar la caja abierta declarando Bs 500 en efectivo',
     "do $$ declare c integer := (select id from mitsu.caja where estado = 'ABIERTA'); begin call mitsu.sp_cerrar_caja(c, 2, 500, 'Arqueo de prueba'); end $$;
      select estado, total_efectivo, monto_declarado, diferencia from mitsu.caja order by cerrada_en desc nulls last limit 1"],
    ['PA8', 'fn_crear_pedido', 'Pedido web de 1 Taza Nube con retiro en el taller',
     "select mitsu.fn_crear_pedido((select id from mitsu.usuario where correo = 'cliente@mitsu.test'), (select c.id from mitsu.cliente c join mitsu.usuario u on u.id = c.usuario_id where u.correo = 'cliente@mitsu.test'), 'RETIRO', null, '72040001', null, jsonb_build_array(jsonb_build_object('pieza_id', (select id from mitsu.pieza where codigo = 'TZ-001'), 'cantidad', 1))) as pedido_id;
      select numero, estado, total, expira_en::time(0) as reservado_hasta from mitsu.pedido order by id desc limit 1"],
    ['PA9', 'fn_registrar_pago', 'Pedido web nuevo pagado con tarjeta (aprobado)',
     "with p as (select mitsu.fn_crear_pedido((select id from mitsu.usuario where correo = 'cliente@mitsu.test'), (select c.id from mitsu.cliente c join mitsu.usuario u on u.id = c.usuario_id where u.correo = 'cliente@mitsu.test'), 'RETIRO', null, '72040001', null, jsonb_build_array(jsonb_build_object('pieza_id', (select id from mitsu.pieza where codigo = 'TZ-001'), 'cantidad', 1))) as id)
      select p.id as pedido_id, mitsu.fn_registrar_pago(p.id, 'TARJETA', 'APROBADO', 'AUT-PRUEBA', 'Visa', '4242', 'DANIELA RIVERO', null, 'Aprobado') as pago_id from p;
      select numero, estado, pagado_en::time(0) as pagado from mitsu.pedido order by id desc limit 1"],
    ['PA11', 'fn_cancelar_pedido', 'Cancelar el pedido en verificación de la demostración',
     "select mitsu.fn_cancelar_pedido((select id from mitsu.pedido where estado = 'EN_VERIFICACION' order by id limit 1), 1, 'Cliente desistió'),
             (select count(*) from mitsu.movimiento_pieza where tipo = 'DEVOLUCION_WEB') as devoluciones"],
];
// La caja de la venta (PA1) debe estar abierta: si no lo está, se abre dentro de la transacción
$procedimientos = [];
foreach ($pas as [$id, $nombre, $desc, $q]) {
    $pre = "insert into mitsu.caja (usuario_id, monto_inicial) select 2, 200 where not exists (select 1 from mitsu.caja where estado = 'ABIERTA'); ";
    $procedimientos[] = ['id' => $id, 'nombre' => $nombre, 'descripcion' => $desc, 'sql' => $q] + ejecutar($pdo, $pre . $q);
}

// 3) Triggers: cada regla de negocio intentando romperse
$tg = [
    ['RN04 tg_reserva_validar', 'Reservar una clase que ya pasó', "insert into mitsu.reserva (sesion_id, alumno_id) values ((select min(id) from mitsu.sesion_clase where fecha < mitsu.hoy_bo()), 1)"],
    ['RN04 tg_reserva_validar', 'Reservar dos veces la misma clase', "insert into mitsu.reserva (sesion_id, alumno_id) select sesion_id, alumno_id from mitsu.reserva r join mitsu.sesion_clase s on s.id = r.sesion_id where r.estado = 'RESERVADA' and s.fecha > mitsu.hoy_bo() limit 1"],
    ['RN06 tg_movimiento_material', 'Consumir más arcilla de la que hay', "insert into mitsu.movimiento_material (material_id, tipo, cantidad, motivo, usuario_id) values ((select id from mitsu.material where nombre = 'Arcilla roja'), 'CONSUMO', 9999, 'Prueba', 2)"],
    ['RN07 tg_stock_protegido', 'Cambiar el stock de una pieza a mano', "update mitsu.pieza set stock = 100 where id = 1"],
    ['RN08 tg_detalle_venta_precio', 'Intentar vender a otro precio (el trigger copia el real)', "insert into mitsu.venta (caja_id, cliente_id, usuario_id, metodo_pago_id) select id, 1, 2, 1 from mitsu.caja where estado = 'ABIERTA'; insert into mitsu.detalle_venta (venta_id, pieza_id, cantidad, precio_unitario) values ((select max(id) from mitsu.venta), (select id from mitsu.pieza where codigo = 'TZ-002'), 1, 1); select numero, total from mitsu.venta order by id desc limit 1"],
    ['RN09 tg_venta_reglas', 'Modificar una venta anulada', "update mitsu.venta set total = 0 where estado = 'ANULADA'"],
    ['RN02 tg_ultimo_admin', 'Desactivar al único administrador', "update mitsu.usuario set estado = 'INACTIVO' where correo = 'admin@mitsu.test'"],
    ['RN03 horario_sin_cruce_maestra', 'Horario cruzado para la misma maestra', "insert into mitsu.horario (taller_id, maestra_id, dia_semana, hora_inicio, hora_fin, cupo) values (1, 1, 2, '11:00', '13:00', 5)"],
    ['ux_material_nombre (normalizar)', 'Material con el mismo nombre en mayúsculas y con tilde', "insert into mitsu.material (categoria_id, unidad_id, nombre) values (1, 1, 'ARCÍLLA ROJA')"],
    ['tg_pedido_estado', 'Pasar un pedido entregado a pagado', "update mitsu.pedido set estado = 'PAGADO' where estado = 'ENTREGADO'"],
    ['Mínimo privilegio', 'El usuario de la aplicación intenta borrar una tabla', "drop table mitsu.bitacora"],
];
$triggers = [];
foreach ($tg as [$regla, $desc, $q]) {
    $pre = "insert into mitsu.caja (usuario_id, monto_inicial) select 2, 200 where not exists (select 1 from mitsu.caja where estado = 'ABIERTA'); ";
    $triggers[] = ['regla' => $regla, 'descripcion' => $desc, 'sql' => $q] + ejecutar($pdo, $pre . $q, 3);
}

// 4) Tabla de volumen: filas actuales y tamaño en disco
$volumen = $pdo->query("select c.relname as tabla, c.reltuples::bigint as estimado, pg_total_relation_size(c.oid) as bytes
                         from pg_class c join pg_namespace n on n.oid = c.relnamespace
                        where n.nspname = 'mitsu' and c.relkind = 'r' order by c.relname")->fetchAll();
foreach ($volumen as &$v) {
    $v['filas'] = (int) $pdo->query('select count(*) from mitsu.' . $v['tabla'])->fetchColumn();
}

@mkdir(__DIR__ . '/datos', 0777, true);
file_put_contents(__DIR__ . '/datos/bd.json', json_encode(compact('consultas', 'procedimientos', 'triggers', 'volumen'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
printf("%d consultas (%d con error) · %d procedimientos (%d con error) · %d triggers · %d tablas\n",
    count($consultas), count(array_filter($consultas, fn($c) => !$c['ok'])), count($procedimientos), count(array_filter($procedimientos, fn($c) => !$c['ok'])), count($triggers), count($volumen));
foreach (array_merge($consultas, $procedimientos) as $c) {
    if (!$c['ok']) {
        echo "  ERROR {$c['id']}: {$c['error']}\n";
    }
}
foreach ($triggers as $t) {
    echo '  ', $t['ok'] ? 'PASA   ' : 'BLOQUEA', " {$t['descripcion']}: ", $t['ok'] ? json_encode($t['filas'], JSON_UNESCAPED_UNICODE) : $t['error'], "\n";
}
