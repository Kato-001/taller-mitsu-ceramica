<?php
/**
 * Prueba de la tienda en línea y la pasarela de pago (CU20, CU21, CU22).
 *
 *   php Docs/pruebas/probar_tienda.php [http://127.0.0.1:8077]
 */

declare(strict_types=1);

$base = $argv[1] ?? 'http://127.0.0.1:8077';
$fallos = 0;

function pedir(string $url, string $jar, ?array $post = null, bool $seguir = false): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_FOLLOWLOCATION => $seguir, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60]);
    if ($post !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $html = (string) curl_exec($c);
    $r = [curl_getinfo($c, CURLINFO_HTTP_CODE), $html, (string) curl_getinfo($c, CURLINFO_REDIRECT_URL)];
    curl_close($c);
    return $r;
}

function token(string $base, string $jar, string $ruta = '/tienda'): string
{
    [, $html] = pedir($base . $ruta, $jar);
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

function post(string $base, string $jar, string $ruta, array $datos): array
{
    [$cod, , $destino] = pedir($base . $ruta, $jar, ['_csrf' => token($base, $jar)] + $datos);
    [, $html] = $destino ? pedir($destino, $jar, null, true) : [0, ''];
    preg_match('/"mensaje":"((?:[^"\\\\]|\\\\.)*)"/', $html, $m);
    return [$cod, (string) json_decode('"' . ($m[1] ?? '') . '"'), $destino, $html];
}

function verificar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallos;
    $fallos += $ok ? 0 : 1;
    printf("%s  %-60s %s\n", $ok ? 'OK   ' : 'FALLA', $nombre, mb_substr($detalle, 0, 90));
}

function pedidoDe(string $url): int
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    return (int) ($q['pedido'] ?? $q['id'] ?? 0);
}

// --- Visitante sin cuenta: ve la tienda primero
$anon = tempnam(sys_get_temp_dir(), 'mt');
foreach (['/', '/tienda', '/tienda/pieza?id=1', '/tienda/carrito', '/tienda/ingresar', '/tienda/registro'] as $r) {
    [$cod] = pedir($base . $r, $anon);
    verificar("Público: $r", $cod === 200, "HTTP $cod");
}
[, $html] = pedir("$base/", $anon);
verificar('La portada muestra piezas y clases', str_contains($html, 'Piezas disponibles') && str_contains($html, 'Clases de cerámica'));
[, $msg] = post($base, $anon, '/tienda/carrito/agregar', ['pieza_id' => 3, 'cantidad' => 1]);
verificar('CU20 Visitante agrega al carrito sin cuenta', str_contains($msg, 'Agregaste'), $msg);
[$cod, , $dest] = pedir("$base/tienda/checkout", $anon);
verificar('CU20 Para pagar pide ingresar', $cod === 302 && str_contains($dest, '/tienda/ingresar'), $dest);
// Ingresa: vuelve al checkout y conserva el carrito
[, , $dest] = pedir("$base/login", $anon, ['_csrf' => token($base, $anon, '/tienda/ingresar'), 'origen' => 'tienda', 'correo' => 'cliente@mitsu.test', 'password' => 'Cliente2026!']);
verificar('CU01 Tras ingresar vuelve al checkout', str_contains($dest, '/tienda/checkout'), $dest);
[$cod, $html] = pedir("$base/tienda/checkout", $anon);
verificar('CU20 El carrito se conserva al ingresar', $cod === 200 && str_contains($html, 'Vasija'), "HTTP $cod");

// --- Tarjeta: rechazada y luego aprobada
[, $msg, $dest] = post($base, $anon, '/tienda/checkout', ['entrega' => 'RETIRO', 'telefono' => '72040001', 'direccion' => '', 'nota' => 'Prueba automática']);
$pedido = pedidoDe($dest);
verificar('CU20 Crear pedido (reserva el stock)', $pedido > 0 && str_contains($msg, 'Pedido creado'), $msg);
[, $msg] = post($base, $anon, '/pago/tarjeta', ['pedido_id' => $pedido, 'numero' => '4242 4242 4242 4241', 'titular' => 'Daniela Rivero', 'vence' => '12/30', 'cvv' => '123']);
verificar('CU21 Rechaza número que no pasa Luhn', str_contains($msg, 'no es válido'), $msg);
[, $msg] = post($base, $anon, '/pago/tarjeta', ['pedido_id' => $pedido, 'numero' => '4242 4242 4242 4242', 'titular' => 'Daniela Rivero', 'vence' => '01/20', 'cvv' => '123']);
verificar('CU21 Rechaza tarjeta vencida', str_contains($msg, 'vencida'), $msg);
[, $msg] = post($base, $anon, '/pago/tarjeta', ['pedido_id' => $pedido, 'numero' => '4000 0000 0000 0002', 'titular' => 'Daniela Rivero', 'vence' => '12/30', 'cvv' => '123']);
verificar('CU21 Tarjeta sin fondos: pago rechazado', str_contains($msg, 'rechazado'), $msg);
[, $msg, $dest, $html] = post($base, $anon, '/pago/tarjeta', ['pedido_id' => $pedido, 'numero' => '4242 4242 4242 4242', 'titular' => 'Daniela Rivero', 'vence' => '12/30', 'cvv' => '123']);
verificar('CU21 Tarjeta válida: pago aprobado', str_contains($msg, 'aprobado'), $msg);
verificar('CU21 Comprobante muestra solo los 4 últimos dígitos', str_contains($html, '•••• 4242') && !str_contains($html, '4242 4242 4242 4242') && str_contains($html, 'COMPROBANTE DE PAGO'));
[, $msg] = post($base, $anon, '/pago/tarjeta', ['pedido_id' => $pedido, 'numero' => '4242 4242 4242 4242', 'titular' => 'Daniela Rivero', 'vence' => '12/30', 'cvv' => '123']);
verificar('CU21 No se cobra dos veces el mismo pedido', str_contains($msg, 'no está esperando pago'), $msg);

// --- PayPal
post($base, $anon, '/tienda/carrito/agregar', ['pieza_id' => 2, 'cantidad' => 1]);
[, , $dest] = post($base, $anon, '/tienda/checkout', ['entrega' => 'ENVIO', 'telefono' => '72040001', 'direccion' => 'Av. Busch, 3er anillo #45', 'nota' => '']);
$pp = pedidoDe($dest);
[$cod] = pedir("$base/pago/paypal?pedido=$pp", $anon);
verificar('CU21 Pantalla de PayPal (sandbox)', $cod === 200, "HTTP $cod");
[, $msg] = post($base, $anon, '/pago/paypal', ['pedido_id' => $pp, 'cuenta' => 'daniela@paypal.test', 'clave' => 'prueba', 'accion' => 'aprobar']);
verificar('CU21 Pago con PayPal aprobado', str_contains($msg, 'aprobado'), $msg);

// --- QR: el cliente avisa y el taller confirma
post($base, $anon, '/tienda/carrito/agregar', ['pieza_id' => 5, 'cantidad' => 1]);
[, , $dest] = post($base, $anon, '/tienda/checkout', ['entrega' => 'RETIRO', 'telefono' => '72040001', 'direccion' => '', 'nota' => '']);
$qr = pedidoDe($dest);
[, $html] = pedir("$base/pago?pedido=$qr", $anon);
verificar('CU21 La pasarela muestra el QR del taller', str_contains($html, 'qr-taller.jpg'));
[, $msg] = post($base, $anon, '/pago/qr', ['pedido_id' => $qr, 'comprobante' => '784512369']);
verificar('CU21 Aviso de pago por QR queda en verificación', str_contains($msg, 'confirmará'), $msg);

// --- Pedido que se cancela: vuelve el stock
post($base, $anon, '/tienda/carrito/agregar', ['pieza_id' => 1, 'cantidad' => 1]);
[, , $dest] = post($base, $anon, '/tienda/checkout', ['entrega' => 'RETIRO', 'telefono' => '72040001', 'direccion' => '', 'nota' => '']);
$cancelar = pedidoDe($dest);
[, $msg] = post($base, $anon, '/mis-pedidos/cancelar', ['id' => $cancelar]);
verificar('CU20 El cliente cancela un pedido impago', str_contains($msg, 'cancelado'), $msg);

// --- Seguridad: el cliente no entra al panel ni ve pedidos ajenos
[$cod] = pedir("$base/panel", $anon);
verificar('Seguridad: cliente no entra al panel', $cod === 403, "HTTP $cod");
[$cod] = pedir("$base/mis-pedidos/ver?id=999999", $anon);
verificar('Seguridad: pedido inexistente o ajeno → 404', $cod === 404, "HTTP $cod");

// --- El taller confirma el pago por QR (CU22)
$staff = tempnam(sys_get_temp_dir(), 'mt');
pedir("$base/login", $staff, ['_csrf' => token($base, $staff, '/login'), 'correo' => 'ventas@mitsu.test', 'password' => 'Ventas2026!']);
[$cod, $html] = pedir("$base/pedidos/ver?id=$qr", $staff);
preg_match('/name="pago_id" value="(\d+)"/', $html, $m);
[, $msg] = post($base, $staff, '/pedidos/verificar-qr', ['pago_id' => $m[1] ?? 0, 'pedido_id' => $qr, 'decision' => 'aprobar']);
verificar('CU22 El taller confirma el depósito QR', str_contains($msg, 'Pago confirmado'), $msg);
[, $msg] = post($base, $staff, '/pedidos/entregar', ['id' => $qr]);
verificar('CU22 Marcar pedido como entregado', str_contains($msg, 'entregado'), $msg);
[, $msg] = post($base, $staff, '/pedidos/cancelar', ['id' => $qr, 'motivo' => 'Prueba de cancelar entregado']);
verificar('CU22 Un pedido entregado no se cancela', str_contains($msg, 'entregado'), $msg);
[$cod] = pedir("$base/pedidos", $staff);
verificar('CU22 Listado de pedidos web', $cod === 200, "HTTP $cod");

echo $fallos ? "\n$fallos FALLA(S)\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);
