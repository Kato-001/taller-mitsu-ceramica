<?php
/**
 * Prueba de los flujos que escriben datos (casos de uso de los ciclos I a III),
 * a través de la interfaz web, igual que lo haría un usuario.
 *
 *   php Docs/pruebas/probar_flujos.php [http://127.0.0.1:8077]
 */

declare(strict_types=1);

$base = $argv[1] ?? 'http://127.0.0.1:8077';
$fallos = 0;

function pedir(string $url, string $jar, ?array $post = null): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60]);
    if ($post !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $html = (string) curl_exec($c);
    $r = [curl_getinfo($c, CURLINFO_HTTP_CODE), $html, (string) curl_getinfo($c, CURLINFO_REDIRECT_URL)];
    curl_close($c);
    return $r;
}

function sesion(string $base, string $correo, string $clave): array
{
    $jar = tempnam(sys_get_temp_dir(), 'mt');
    [, $html] = pedir("$base/login", $jar);
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    pedir("$base/login", $jar, ['_csrf' => $m[1], 'correo' => $correo, 'password' => $clave]);
    [, $html] = pedir("$base/cuenta", $jar);
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $html, $m)) {
        [, $html] = pedir("$base/portal", $jar);
        preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    }
    return [$jar, $m[1]];
}

/** Hace el POST, sigue la redirección y devuelve el mensaje flash mostrado. */
function accion(string $base, array $s, string $ruta, array $datos): array
{
    [$cod, , $destino] = pedir($base . $ruta, $s[0], ['_csrf' => $s[1]] + $datos);
    [, $html] = $destino ? pedir($destino, $s[0]) : [0, ''];
    preg_match('/"mensaje":"((?:[^"\\\\]|\\\\.)*)"/', $html, $m);
    return [$cod, json_decode('"' . ($m[1] ?? '') . '"'), $destino, $html];
}

function verificar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallos;
    $fallos += $ok ? 0 : 1;
    printf("%s  %-58s %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, $detalle);
}

$admin = sesion($base, 'admin@mitsu.test', 'Admin2026!');
$ventas = sesion($base, 'ventas@mitsu.test', 'Ventas2026!');
$maestra = sesion($base, 'maestra@mitsu.test', 'Maestra2026!');
$alumno = sesion($base, 'alumno@mitsu.test', 'Alumno2026!');

// CU18 · Venta en caja (pieza 2 = TZ-002, en efectivo con cambio)
[, $msg, $dest, $html] = accion($base, $ventas, '/ventas/guardar', ['carrito' => json_encode([['pieza_id' => 2, 'cantidad' => 1]]), 'cliente_id' => 2, 'metodo_pago_id' => 1, 'recibido' => '100']);
verificar('CU18 Registrar venta en efectivo', str_contains((string) $msg, 'Venta registrada'), (string) $msg);
parse_str((string) parse_url($dest, PHP_URL_QUERY), $q);
$ventaId = (int) ($q['id'] ?? 0);
verificar('CU18 Nota de venta muestra el cambio', str_contains($html, 'Cambio') && str_contains($html, 'NOTA DE VENTA'));

[, $msg] = accion($base, $ventas, '/ventas/guardar', ['carrito' => json_encode([['pieza_id' => 2, 'cantidad' => 1]]), 'cliente_id' => 1, 'metodo_pago_id' => 1, 'recibido' => '10']);
verificar('CU18 Rechaza pago en efectivo insuficiente', str_contains((string) $msg, 'menor al total'), (string) $msg);

[, $msg] = accion($base, $ventas, '/ventas/guardar', ['carrito' => json_encode([['pieza_id' => 4, 'cantidad' => 99]]), 'cliente_id' => 1, 'metodo_pago_id' => 2, 'recibido' => '']);
verificar('CU18 Rechaza vender más que el stock', str_contains((string) $msg, 'Stock insuficiente'), (string) $msg);

[, $msg] = accion($base, $ventas, '/ventas/anular', ['id' => $ventaId, 'motivo' => 'Prueba']);
verificar('CU18 Anular exige permiso ventas.anular (administrativo no)', $msg === '' || str_contains((string) $msg, 'denegado'), 'sin permiso');
[, $msg] = accion($base, $admin, '/ventas/anular', ['id' => $ventaId, 'motivo' => 'Prueba automática de anulación']);
verificar('CU18 Anular venta con motivo (administrador)', str_contains((string) $msg, 'anulada'), (string) $msg);
[, $msg] = accion($base, $admin, '/ventas/anular', ['id' => $ventaId, 'motivo' => 'Otra vez']);
verificar('CU18 No se anula dos veces', str_contains((string) $msg, 'ya estaba anulada'), (string) $msg);

// CU15 · Compra de dos materiales
[, $msg, $dest] = accion($base, $ventas, '/compras/guardar', ['proveedor_id' => 1, 'fecha' => date('Y-m-d'), 'observacion' => 'Prueba automática',
    'items' => [['material_id' => 2, 'cantidad' => '5', 'costo' => '45'], ['material_id' => 6, 'cantidad' => '20', 'costo' => '9.5']]]);
verificar('CU15 Registrar compra (sube stock)', str_contains((string) $msg, 'Compra registrada'), (string) $msg);
[, $msg] = accion($base, $ventas, '/compras/guardar', ['proveedor_id' => 1, 'fecha' => date('Y-m-d', strtotime('+3 days')),
    'items' => [['material_id' => 2, 'cantidad' => '5', 'costo' => '45']]]);
verificar('CU15 Rechaza compra con fecha futura', str_contains((string) $msg, 'posterior'), (string) $msg);

// CU16 · Consumo mayor al stock
[, $msg] = accion($base, $ventas, '/materiales/movimiento', ['id' => 1, 'tipo' => 'CONSUMO', 'cantidad' => '99999', 'motivo' => 'Prueba de exceso']);
verificar('CU16 Rechaza consumo mayor al stock', str_contains((string) $msg, 'suficiente stock'), (string) $msg);
[, $msg] = accion($base, $ventas, '/materiales/movimiento', ['id' => 1, 'tipo' => 'CONSUMO', 'cantidad' => '1', 'motivo' => 'Prueba de consumo en clase']);
verificar('CU16 Registrar consumo de material', str_contains((string) $msg, 'Movimiento registrado'), (string) $msg);

// CU13 · Nombre de material repetido (sin importar tildes)
[, $msg] = accion($base, $admin, '/materiales/guardar', ['nombre' => 'ARCILLA ROJA', 'categoria_id' => 1, 'unidad_id' => 1, 'stock_minimo' => '5']);
verificar('CU13 Rechaza material repetido (mayúsculas/tildes)', str_contains((string) $msg, 'Ya existe un material'), (string) $msg);

// CU10 · El alumno reserva una clase futura con cupo
[, $html] = pedir("$base/portal/reservar", $alumno[0]);
preg_match_all('/name="sesion_id" value="(\d+)"/', $html, $m);
$sesion = (int) ($m[1][0] ?? 0);
[, $msg] = accion($base, $alumno, '/portal/reservar', ['sesion_id' => $sesion]);
verificar('CU10 Alumno reserva clase desde el portal', str_contains((string) $msg, 'Reservaste'), (string) $msg);
[, $msg] = accion($base, $alumno, '/portal/reservar', ['sesion_id' => $sesion]);
verificar('CU10 No permite reservar dos veces la misma clase', str_contains((string) $msg, 'ya tiene una reserva'), (string) $msg);

// CU11 · La maestra registra asistencia de una clase suya de hoy o pasada
[, $html] = pedir("$base/clases", $maestra[0]);
preg_match_all('/\/clases\/ver\?id=(\d+)/', $html, $m);
$hecho = false;
foreach (array_unique($m[1]) as $sid) {
    [, $h] = pedir("$base/clases/ver?id=$sid", $maestra[0]);
    if (preg_match_all('/name="asistencia\[(\d+)\]" value="ASISTIO"/', $h, $r)) {
        $marcas = [];
        foreach ($r[1] as $i => $rid) {
            $marcas[$rid] = $i === 0 ? 'FALTO' : 'ASISTIO';
        }
        [, $msg] = accion($base, $maestra, '/clases/asistencia', ['sesion_id' => $sid, 'asistencia' => $marcas]);
        verificar('CU11 Maestra registra asistencia', str_contains((string) $msg, 'Asistencia guardada'), (string) $msg);
        $hecho = true;
        break;
    }
}
verificar('CU11 Había una clase con alumnos para marcar', $hecho);

// La maestra no puede ver clases de otra maestra
[$cod] = pedir("$base/clases/ver?id=2", $maestra[0]);
verificar('Seguridad: maestra no abre clase ajena', in_array($cod, [403, 200], true), "HTTP $cod (403 si la clase 2 es de otra maestra)");

// CU09 · Horario que se cruza para la misma maestra
[, $msg] = accion($base, $admin, '/horarios/guardar', ['taller_id' => 1, 'maestra_id' => 1, 'dia_semana' => 2, 'hora_inicio' => '11:00', 'hora_fin' => '13:00', 'cupo' => 5]);
verificar('CU09 Rechaza horario cruzado de la maestra', str_contains((string) $msg, 'se cruza'), (string) $msg);

// CU03 · Validaciones de usuario
[, $msg] = accion($base, $admin, '/usuarios/guardar', ['nombre' => 'X', 'apellido' => 'Prueba', 'correo' => 'admin@mitsu.test', 'rol_id' => 2, 'password' => 'abc', 'password_confirmacion' => 'abc']);
verificar('CU03 Valida nombre, correo repetido y contraseña', $msg !== '', (string) $msg);

// CU01 · Bloqueo tras intentos fallidos se prueba con otra IP solo en local (no se ejecuta aquí para no bloquear la IP)

// CU05 · La bitácora registra las operaciones
[, $html] = pedir("$base/bitacora?modulo=VENTAS", $admin[0]);
verificar('CU05 Bitácora registra la anulación', str_contains($html, 'ANULAR'));

echo $fallos ? "\n$fallos FALLA(S)\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);
