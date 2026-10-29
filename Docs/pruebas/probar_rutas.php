<?php
/**
 * Prueba de humo: inicia sesión con cada cuenta de demostración y abre todas
 * las pantallas permitidas y prohibidas, verificando el código HTTP.
 *
 *   php -S 127.0.0.1:8077 router.php        (en otra terminal)
 *   php Docs/pruebas/probar_rutas.php [http://127.0.0.1:8077]
 */

declare(strict_types=1);

$base = $argv[1] ?? 'http://127.0.0.1:8077';
$fallos = 0;

function pedir(string $url, string $cookies, ?array $post = null): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => false,
        CURLOPT_COOKIEJAR => $cookies, CURLOPT_COOKIEFILE => $cookies, CURLOPT_TIMEOUT => 60,
    ]);
    if ($post !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $html = (string) curl_exec($c);
    $r = [curl_getinfo($c, CURLINFO_HTTP_CODE), $html, (string) curl_getinfo($c, CURLINFO_REDIRECT_URL)];
    curl_close($c);
    return $r;
}

function token(string $html): string
{
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

$cuentas = [
    'admin@mitsu.test'   => ['Admin2026!',   ['/panel', '/usuarios', '/usuarios/nuevo', '/usuarios/editar?id=2', '/roles', '/bitacora', '/configuracion', '/cuenta',
                                              '/maestras', '/maestras/nueva', '/maestras/editar?id=1', '/talleres', '/talleres?editar=1', '/horarios', '/clases', '/clases?semana=' . date('Y-m-d', strtotime('+7 days')),
                                              '/clases/ver?id=1', '/alumnos', '/alumnos/ver?id=1', '/alumnos/nuevo', '/alumnos/editar?id=1',
                                              '/materiales', '/materiales?alerta=1', '/materiales/form', '/materiales/form?id=1', '/materiales/kardex?id=1', '/proveedores', '/compras', '/compras/nueva', '/compras/ver?id=1',
                                              '/piezas', '/piezas/form', '/piezas/form?id=1', '/piezas/kardex?id=1', '/ventas/nueva', '/ventas', '/ventas/ver?id=1', '/caja', '/clientes'],
                                             ['/portal']],
    'ventas@mitsu.test'  => ['Ventas2026!',  ['/panel', '/clases', '/alumnos', '/materiales', '/compras', '/piezas', '/ventas/nueva', '/caja', '/clientes', '/cuenta'],
                                             ['/usuarios', '/roles', '/bitacora', '/configuracion', '/maestras', '/talleres', '/portal']],
    'maestra@mitsu.test' => ['Maestra2026!', ['/panel', '/clases', '/cuenta'],
                                             ['/usuarios', '/alumnos', '/ventas/nueva', '/materiales', '/compras', '/caja', '/portal']],
    'alumno@mitsu.test'  => ['Alumno2026!',  ['/portal', '/portal/reservar', '/portal/perfil'],
                                             ['/panel', '/usuarios', '/clases', '/ventas', '/materiales']],
];

foreach ($cuentas as $correo => [$clave, $permitidas, $prohibidas]) {
    $jar = tempnam(sys_get_temp_dir(), 'mt');
    [, $html] = pedir("$base/login", $jar);
    [$cod, , $destino] = pedir("$base/login", $jar, ['_csrf' => token($html), 'correo' => $correo, 'password' => $clave]);
    $ok = $cod === 302 && !str_ends_with($destino, '/login');
    printf("%-22s login %d → %s %s\n", $correo, $cod, parse_url($destino, PHP_URL_PATH), $ok ? 'OK' : 'FALLA');
    $fallos += $ok ? 0 : 1;
    foreach ($permitidas as $ruta) {
        [$cod, $html] = pedir($base . $ruta, $jar);
        $error = preg_match('/Fatal error|Warning:|Notice:|Deprecated:|Ocurrió un error inesperado/', $html);
        if ($cod !== 200 || $error) {
            $fallos++;
            preg_match('/(Fatal error|Warning|Notice|Deprecated)[^<]{0,220}/', strip_tags($html), $m);
            printf("   FALLA %-45s %d %s\n", $ruta, $cod, $m[0] ?? '');
        }
    }
    foreach ($prohibidas as $ruta) {
        [$cod] = pedir($base . $ruta, $jar);
        if ($cod !== 403) {
            $fallos++;
            printf("   FALLA acceso indebido %-30s %d (se esperaba 403)\n", $ruta, $cod);
        }
    }
    printf("   %d permitidas y %d prohibidas revisadas\n", count($permitidas), count($prohibidas));
    @unlink($jar);
}

// Sin sesión: todo redirige al login; una ruta inexistente da 404
[$cod, , $dest] = pedir("$base/panel", tempnam(sys_get_temp_dir(), 'mt'));
printf("Sin sesión /panel → %d %s\n", $cod, parse_url($dest, PHP_URL_PATH));
$fallos += ($cod === 302 && str_ends_with($dest, '/login')) ? 0 : 1;
[$cod] = pedir("$base/no-existe", tempnam(sys_get_temp_dir(), 'mt'));
printf("Ruta inexistente → %d\n", $cod);
$fallos += $cod === 404 ? 0 : 1;
// POST sin token CSRF → 419
[$cod] = pedir("$base/login", tempnam(sys_get_temp_dir(), 'mt'), ['correo' => 'x@x.com', 'password' => 'x']);
printf("POST sin CSRF → %d\n", $cod);
$fallos += $cod === 419 ? 0 : 1;

echo $fallos ? "\n$fallos FALLA(S)\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);
