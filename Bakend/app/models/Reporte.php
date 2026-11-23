<?php
/**
 * Consultas y reportes personalizados (CU23, CU24).
 *
 * El usuario elige qué consultar, el campo, la condición y el valor. El SQL se arma
 * solo con los campos y condiciones declarados en CatalogoReportes (lista blanca)
 * y los valores siempre viajan como parámetros.
 */

declare(strict_types=1);

final class Reporte
{
    public const LIMITE = 2000;
    public const MAX_FILTROS = 12;

    public const OPERADORES = [
        'texto'  => ['contiene' => 'contiene', 'igual' => 'es igual a', 'distinto' => 'es distinto de', 'empieza' => 'empieza con', 'no_contiene' => 'no contiene', 'vacio' => 'está vacío', 'no_vacio' => 'no está vacío'],
        'opcion' => ['igual' => 'es', 'distinto' => 'no es'],
        'numero' => ['mayor' => 'mayor que (>)', 'mayor_igual' => 'mayor o igual que (≥)', 'menor' => 'menor que (<)', 'menor_igual' => 'menor o igual que (≤)', 'igual' => 'igual a (=)', 'distinto' => 'distinto de (≠)', 'entre' => 'entre'],
        'fecha'  => ['entre' => 'entre', 'desde' => 'desde', 'hasta' => 'hasta', 'igual' => 'el día', 'hoy' => 'es hoy', 'ayer' => 'fue ayer', 'ultimos7' => 'últimos 7 días', 'ultimos30' => 'últimos 30 días', 'este_mes' => 'este mes', 'mes_pasado' => 'mes pasado', 'este_anio' => 'este año'],
    ];
    /** Condiciones que no necesitan valor. */
    public const SIN_VALOR = ['vacio', 'no_vacio', 'hoy', 'ayer', 'ultimos7', 'ultimos30', 'este_mes', 'mes_pasado', 'este_anio'];

    /** Reportes que el usuario actual puede consultar. */
    public static function permitidos(): array
    {
        return array_filter(CatalogoReportes::tipos(), fn($t) => !isset($t['permiso']) || Auth::puede($t['permiso']));
    }

    /** Familia de condiciones de un campo. */
    public static function familia(string $tipoCampo): string
    {
        return $tipoCampo === 'dinero' ? 'numero' : $tipoCampo;
    }

    /** Valores que existen de cada campo de tipo opción, para las listas desplegables. */
    public static function opciones(string $tipo): array
    {
        $t = CatalogoReportes::tipos()[$tipo];
        $partes = [];
        foreach ($t['campos'] as $k => $c) {
            if ($c[2] === 'opcion') {
                $partes[] = "select '$k' as campo, cast({$c[1]} as text) as valor from {$t['from']}";
            }
        }
        $r = [];
        if ($partes) {
            $sql = 'select distinct campo, valor from (' . implode(' union all ', $partes) . ') x where valor is not null order by campo, valor limit 400';
            foreach (Database::filas($sql) as $f) {
                $r[$f['campo']][] = $f['valor'];
            }
        }
        return $r;
    }

    /** Limpia lo recibido: descarta reportes, campos y condiciones que no existen. */
    public static function normalizar(array $q): array
    {
        $tipos = self::permitidos();
        $tipo = is_string($q['tipo'] ?? null) && isset($tipos[$q['tipo']]) ? $q['tipo'] : 'piezas';
        $campos = $tipos[$tipo]['campos'];
        $texto = fn($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 120) : '';
        $filtros = [];
        foreach (array_slice(is_array($q['f'] ?? null) ? array_values($q['f']) : [], 0, self::MAX_FILTROS) as $f) {
            if (!is_array($f) || !is_string($f['c'] ?? null) || !isset($campos[$f['c']])) {
                continue;
            }
            $ops = self::OPERADORES[self::familia($campos[$f['c']][2])];
            $o = is_string($f['o'] ?? null) && isset($ops[$f['o']]) ? $f['o'] : array_key_first($ops);
            $filtros[] = ['c' => $f['c'], 'o' => $o, 'v' => $texto($f['v'] ?? ''), 'w' => $texto($f['w'] ?? '')];
        }
        $elegidas = is_array($q['col'] ?? null) ? $q['col'] : [];
        $columnas = array_values(array_filter(array_keys($campos), fn($k) => in_array($k, $elegidas, true)));
        return [
            'tipo'    => $tipo,
            'f'       => $filtros,
            'con'     => ($q['con'] ?? '') === 'o' ? 'o' : 'y',
            'col'     => $columnas ?: array_keys($campos),
            'orden'   => is_string($q['orden'] ?? null) && isset($campos[$q['orden']]) ? $q['orden'] : $tipos[$tipo]['orden'],
            'dir'     => in_array($q['dir'] ?? '', ['asc', 'desc'], true) ? $q['dir'] : $tipos[$tipo]['dir'],
            'agrupar' => is_string($q['agrupar'] ?? null) && isset($campos[$q['agrupar']]) ? $q['agrupar'] : '',
        ];
    }

    /** Ejecuta la consulta ya normalizada y devuelve columnas, filas, totales y descripción. */
    public static function ejecutar(array $q): array
    {
        $t = CatalogoReportes::tipos()[$q['tipo']];
        $campos = $t['campos'];
        [$where, $p, $descripcion] = self::condiciones($q, $campos);
        $columnas = [];
        $sel = [];

        if ($q['agrupar'] !== '') {
            $g = $campos[$q['agrupar']];
            $columnas = ['grupo' => [$g[0], $g[2]], 'registros' => ['Registros', 'numero']];
            $sel = ["{$g[1]} as grupo", 'count(*) as registros'];
            foreach ($campos as $k => $c) {
                if (!empty($c[3]) && $k !== $q['agrupar']) {
                    $columnas[$k] = [$c[0], $c[2]];
                    $sel[] = "sum({$c[1]}) as $k";
                }
            }
            $sql = 'select ' . implode(', ', $sel) . " from {$t['from']} $where group by 1";
            $orden = isset($columnas[$q['orden']]) ? "{$q['orden']} {$q['dir']} nulls last" : 'registros desc, grupo';
            $descripcion[] = 'Agrupado por ' . mb_strtolower($g[0]);
        } else {
            foreach ($campos as $k => $c) {
                $sel[] = "{$c[1]} as $k";
                if (in_array($k, $q['col'], true)) {
                    $columnas[$k] = [$c[0], $c[2]];
                }
            }
            $sql = 'select ' . implode(', ', $sel) . " from {$t['from']} $where";
            $orden = "{$q['orden']} {$q['dir']} nulls last";
        }

        $sumables = array_filter(array_keys($columnas), fn($k) => $k === 'registros' || ($k !== 'grupo' && !empty($campos[$k][3])));
        $sumas = implode('', array_map(fn($k) => ", sum($k) as $k", $sumables));
        $tot = Database::fila("select count(*) as total_filas$sumas from ($sql) x", $p);
        $filas = Database::filas("select * from ($sql) x order by $orden limit " . self::LIMITE, $p);
        return [
            'titulo'      => $t['titulo'],
            'columnas'    => $columnas,
            'filas'       => $filas,
            'total'       => (int) $tot['total_filas'],
            'totales'     => array_intersect_key($tot, array_flip($sumables)),
            'descripcion' => $descripcion ?: ['Sin filtros: todos los registros'],
        ];
    }

    /** Valor listo para mostrar. */
    public static function mostrar(mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        return match ($tipo) {
            'dinero' => bs($v),
            'numero' => cantidad($v),
            'fecha'  => date('d/m/Y', strtotime((string) $v)),
            'opcion' => self::etiqueta((string) $v),
            default  => (string) $v,
        };
    }

    /** Códigos en mayúsculas (EN_VERIFICACION) a texto legible; los nombres quedan igual. */
    public static function etiqueta(string $v): string
    {
        if (!preg_match('/^[A-Z]{3}[A-Z_]*$/', $v) || in_array($v, ['ACTIVO', 'INACTIVO'], true)) {
            return in_array($v, ['ACTIVO', 'INACTIVO'], true) ? ucfirst(strtolower($v)) : $v;
        }
        return Pedido::ESTADOS[$v][0] ?? Pedido::METODOS[$v] ?? ESTADOS[$v][0] ?? Compra::TIPOS[$v] ?? PUBLICOS[$v] ?? NIVELES[$v] ?? ['ASISTIO' => 'Asistió', 'VALIDA' => 'Válida', 'PRODUCCION' => 'Producción', 'ENVIO' => 'Envío', 'AJUSTE_MAS' => 'Ajuste (+)', 'AJUSTE_MENOS' => 'Ajuste (−)'][$v] ?? ucfirst(strtolower(str_replace('_', ' ', $v)));
    }

    /** @return array{0: string, 1: array, 2: string[]} cláusula where, parámetros y descripción */
    private static function condiciones(array $q, array $campos): array
    {
        $w = [];
        $p = [];
        $d = [];
        foreach ($q['f'] as $i => $f) {
            [$etiqueta, $x, $tipo] = $campos[$f['c']];
            $o = $f['o'];
            $fam = self::familia($tipo);
            $sinValor = in_array($o, self::SIN_VALOR, true);
            if (!$sinValor && $f['v'] === '') {
                continue;   // filtro incompleto: se ignora
            }
            $nombre = preg_replace('/ \(.*\)$/', '', self::OPERADORES[$fam][$o]);

            if ($fam === 'numero') {
                $v = str_replace(',', '.', $f['v']);
                $z = str_replace(',', '.', $f['w']);
                if (!is_numeric($v) || ($o === 'entre' && !is_numeric($z))) {
                    throw new AppException("El filtro “{$etiqueta}” necesita un número.");
                }
                $cmp = ['mayor' => '>', 'mayor_igual' => '>=', 'menor' => '<', 'menor_igual' => '<=', 'igual' => '=', 'distinto' => '<>'];
                $p["v$i"] = $v;
                if ($o === 'entre') {
                    $p["w$i"] = $z;
                    $w[] = "$x between cast(:v$i as numeric) and cast(:w$i as numeric)";
                } else {
                    $w[] = "$x {$cmp[$o]} cast(:v$i as numeric)";
                }
                $d[] = "$etiqueta $nombre {$f['v']}" . ($o === 'entre' ? " y {$f['w']}" : '');
            } elseif ($fam === 'fecha' && $sinValor) {
                $h = 'mitsu.hoy_bo()';
                $w[] = match ($o) {
                    'hoy'        => "$x = $h",
                    'ayer'       => "$x = $h - 1",
                    'ultimos7'   => "$x between $h - 6 and $h",
                    'ultimos30'  => "$x between $h - 29 and $h",
                    'este_mes'   => "date_trunc('month', $x) = date_trunc('month', $h)",
                    'mes_pasado' => "date_trunc('month', $x) = date_trunc('month', $h) - interval '1 month'",
                    default      => "date_trunc('year', $x) = date_trunc('year', $h)",
                };
                $d[] = "$etiqueta: $nombre";
            } elseif ($fam === 'fecha') {
                foreach ($o === 'entre' ? [$f['v'], $f['w']] : [$f['v']] as $fecha) {
                    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                        throw new AppException("El filtro “{$etiqueta}” necesita una fecha válida.");
                    }
                }
                $p["v$i"] = $f['v'];
                if ($o === 'entre') {
                    $p["w$i"] = $f['w'];
                    $w[] = "$x between cast(:v$i as date) and cast(:w$i as date)";
                } else {
                    $w[] = "$x " . ['desde' => '>=', 'hasta' => '<=', 'igual' => '='][$o] . " cast(:v$i as date)";
                }
                $d[] = "$etiqueta $nombre " . date('d/m/Y', strtotime($f['v'])) . ($o === 'entre' ? ' y ' . date('d/m/Y', strtotime($f['w'])) : '');
            } elseif ($sinValor) {
                $w[] = "coalesce(cast($x as text), '') " . ($o === 'vacio' ? '=' : '<>') . " ''";
                $d[] = "$etiqueta $nombre";
            } else {
                // position() y left(): un % o un _ escrito por el usuario no actúa como comodín
                $n = "normalizar(cast($x as text))";
                $p["v$i"] = $f['v'];
                $w[] = match ($o) {
                    'igual'       => "$n = normalizar(:v$i)",
                    'distinto'    => "$n <> normalizar(:v$i)",
                    'empieza'     => "left($n, length(normalizar(:v$i))) = normalizar(:v$i)",
                    'no_contiene' => "position(normalizar(:v$i) in $n) = 0",
                    default       => "position(normalizar(:v$i) in $n) > 0",
                };
                $d[] = "$etiqueta $nombre “" . self::etiqueta($f['v']) . '”';
            }
        }
        if (!$w) {
            return ['', [], []];
        }
        $o = $q['con'] === 'o';
        return ['where (' . implode($o ? ') or (' : ') and (', $w) . ')', $p, [implode($o ? ' O ' : ' Y ', $d)]];
    }

    // ------------------------------------------------ Reportes guardados

    public static function guardados(int $usuario): array
    {
        return Database::filas('select id, nombre, tipo, parametros from reporte_guardado where usuario_id = :u order by nombre', ['u' => $usuario]);
    }

    public static function guardar(int $usuario, string $nombre, array $q): int
    {
        return (int) Database::valor(
            'insert into reporte_guardado (usuario_id, nombre, tipo, parametros) values (:u, :n, :t, cast(:p as jsonb)) returning id',
            ['u' => $usuario, 'n' => $nombre, 't' => $q['tipo'], 'p' => json_encode($q, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function eliminar(int $id, int $usuario): void
    {
        Database::query('delete from reporte_guardado where id = :i and usuario_id = :u', ['i' => $id, 'u' => $usuario]);
    }
}
