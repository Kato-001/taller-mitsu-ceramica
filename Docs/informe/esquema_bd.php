<?php
/**
 * Lee del catálogo de PostgreSQL las tablas del esquema mitsu (columnas, tipos,
 * llaves primarias y foráneas) y las guarda en datos/esquema.json para el informe
 * (diagrama relacional, mapeo clase → tabla y diccionario de datos).
 *
 *   php Docs/informe/esquema_bd.php
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

$columnas = $pdo->query("
    select c.relname as tabla, a.attname as columna, format_type(a.atttypid, a.atttypmod) as tipo,
           a.attnotnull as obligatorio, a.attnum as orden
      from pg_attribute a join pg_class c on c.oid = a.attrelid join pg_namespace n on n.oid = c.relnamespace
     where n.nspname = 'mitsu' and c.relkind = 'r' and a.attnum > 0 and not a.attisdropped
     order by c.relname, a.attnum")->fetchAll();

$llaves = $pdo->query("
    select c.relname as tabla, k.contype as tipo, k.conname as nombre,
           (select string_agg(a.attname, ',' order by x.n) from unnest(k.conkey) with ordinality x(att, n)
              join pg_attribute a on a.attrelid = k.conrelid and a.attnum = x.att) as columnas,
           r.relname as referencia
      from pg_constraint k join pg_class c on c.oid = k.conrelid join pg_namespace n on n.oid = c.relnamespace
      left join pg_class r on r.oid = k.confrelid
     where n.nspname = 'mitsu' and k.contype in ('p', 'f', 'u', 'c', 'x')
     order by c.relname, k.contype, k.conname")->fetchAll();

$tablas = [];
foreach ($columnas as $c) {
    $tablas[$c['tabla']]['columnas'][] = ['nombre' => $c['columna'], 'tipo' => $c['tipo'], 'obligatorio' => (bool) $c['obligatorio']];
}
foreach ($llaves as $k) {
    $tablas[$k['tabla']]['restricciones'][] = ['tipo' => $k['tipo'], 'nombre' => $k['nombre'], 'columnas' => $k['columnas'], 'referencia' => $k['referencia']];
}
$cantidad = $pdo->query("select count(*) from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'mitsu' and not t.tgisinternal")->fetchColumn();

file_put_contents(__DIR__ . '/datos/esquema.json', json_encode(['tablas' => $tablas, 'triggers' => (int) $cantidad], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
printf("%d tablas · %d columnas · %d restricciones · %d triggers\n", count($tablas), count($columnas), count($llaves), $cantidad);
