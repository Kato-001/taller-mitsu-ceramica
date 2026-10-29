<?php
/**
 * Ejecuta scripts SQL contra PostgreSQL sin necesidad de psql.
 *
 *   php Bakend/database/ejecutar.php 01_esquema.sql 02_reglas_negocio.sql ...
 *
 * Usa la variable DB_OWNER_URL (dueño del esquema, solo para instalar)
 * definida en el archivo .env de la raíz. Cada archivo corre en una
 * transacción: si algo falla, ese archivo no deja cambios.
 * En 04_usuario_app.sql reemplaza :'clave_app' por APP_DB_PASSWORD.
 */

declare(strict_types=1);

$raiz = dirname(__DIR__, 2);
foreach (is_file("$raiz/.env") ? file("$raiz/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $linea) {
    if (!str_starts_with(trim($linea), '#') && str_contains($linea, '=')) {
        [$k, $v] = array_map('trim', explode('=', $linea, 2));
        getenv($k) === false && putenv("$k=$v");
    }
}

$url = getenv('DB_OWNER_URL') ?: exit("Falta DB_OWNER_URL en .env\n");
$p = parse_url($url);
parse_str($p['query'] ?? '', $q);
$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=%s', $p['host'], $p['port'] ?? 5432, ltrim($p['path'] ?? '/postgres', '/'), $q['sslmode'] ?? 'require'),
    urldecode($p['user']), urldecode($p['pass']),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

foreach (array_slice($argv, 1) as $archivo) {
    $ruta = is_file($archivo) ? $archivo : __DIR__ . "/$archivo";
    $sql = file_get_contents($ruta);
    if (str_contains($sql, ":'clave_app'")) {
        $clave = getenv('APP_DB_PASSWORD') ?: exit("Falta APP_DB_PASSWORD en .env\n");
        $sql = str_replace(":'clave_app'", $pdo->quote($clave), $sql);
    }
    $inicio = microtime(true);
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $pdo->commit();
        printf("OK   %-32s %.1f s\n", basename($ruta), microtime(true) - $inicio);
    } catch (Throwable $e) {
        $pdo->inTransaction() && $pdo->rollBack();
        printf("ERROR %s\n%s\n", basename($ruta), $e->getMessage());
        exit(1);
    }
}
