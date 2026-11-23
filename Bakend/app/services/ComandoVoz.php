<?php
/**
 * CU25 · Interpreta una frase dictada y la convierte en una consulta de reportes.
 * Son reglas fijas (palabras clave y números): no usa inteligencia artificial.
 * Ejemplo: "productos con stock mayor a 20 en pdf".
 */

declare(strict_types=1);

final class ComandoVoz
{
    /** Palabras que identifican cada reporte; se prueba en este orden. */
    private const REPORTES = [
        'vendidos'       => ['productos vendidos', 'piezas vendidas', 'detalle de ventas'],
        'calificaciones' => ['calificacion', 'opinion', 'puntaje', 'estrella'],
        'kardex'         => ['kardex', 'movimiento', 'merma', 'consumo'],
        'reservas'       => ['reserva', 'asistencia', 'asistieron', 'faltaron'],
        'cajas'          => ['caja', 'arqueo'],
        'ventas'         => ['venta'],
        'pedidos'        => ['pedido', 'tienda'],
        'compras'        => ['compra'],
        'materiales'     => ['material', 'insumo', 'inventario', 'arcilla', 'esmalte'],
        'clases'         => ['clase', 'ocupacion'],
        'alumnos'        => ['alumno', 'estudiante'],
        'clientes'       => ['cliente'],
        'bitacora'       => ['bitacora', 'auditoria'],
        'piezas'         => ['producto', 'pieza', 'stock', 'precio'],
    ];

    private const PERIODOS = [
        'hoy' => ['hoy'], 'ayer' => ['ayer'], 'ultimos7' => ['ultima semana', 'ultimos 7 dias', 'ultimos siete dias', 'esta semana'],
        'ultimos30' => ['ultimos 30 dias', 'ultimos treinta dias'], 'mes_pasado' => ['mes pasado'], 'este_mes' => ['este mes', 'del mes'], 'este_anio' => ['este ano', 'del ano'],
    ];

    private const COMPARACIONES = [
        'mayor_igual' => ['mayor o igual', 'al menos', 'minimo de'], 'menor_igual' => ['menor o igual', 'como maximo', 'maximo de'],
        'mayor' => ['mayores', 'mayor', 'mas de', 'superior', 'mas que'], 'menor' => ['menores', 'menor', 'menos de', 'inferior', 'menos que'], 'igual' => ['igual a', 'igual'],
    ];

    private const NUMEROS = ['cero' => 0, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10,
        'quince' => 15, 'veinte' => 20, 'treinta' => 30, 'cuarenta' => 40, 'cincuenta' => 50, 'sesenta' => 60, 'setenta' => 70, 'ochenta' => 80, 'noventa' => 90, 'cien' => 100, 'doscientos' => 200, 'quinientos' => 500, 'mil' => 1000];

    /** Minúsculas, sin tildes ni signos. */
    public static function normalizar(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', '>' => ' mayor ', '<' => ' menor ']);
        $s = (string) preg_replace('/(\d)[.](?=\d{3}\b)/', '$1', $s);
        $s = (string) preg_replace('/[^a-z0-9.,_ -]+/', ' ', $s);
        foreach (self::NUMEROS as $palabra => $n) {
            $s = (string) preg_replace("/\\b$palabra\\b/", (string) $n, $s);
        }
        return trim((string) preg_replace('/\s+/', ' ', $s), ' .,');
    }

    /**
     * @param array $permitidos reportes que puede ver el usuario
     * @param callable $opciones fn(string $tipo): array valores que existen por campo
     * @return array{consulta: array, formato: string} lanza AppException si no reconoce el reporte
     */
    public static function interpretar(string $frase, array $permitidos, callable $opciones): array
    {
        $t = self::normalizar($frase);
        $tipo = null;
        foreach (self::REPORTES as $k => $palabras) {
            if (isset($permitidos[$k]) && self::contiene($t, $palabras)) {
                $tipo = $k;
                break;
            }
        }
        if ($tipo === null) {
            throw new AppException('No entendí qué reporte quiere. Pruebe con: “ventas de este mes”, “productos con stock mayor a 20” o “materiales con stock bajo en PDF”.');
        }
        $campos = $permitidos[$tipo]['campos'];
        $filtros = [];

        // Período: se aplica a la primera fecha del reporte
        $fecha = current(array_filter(array_keys($campos), fn($k) => $campos[$k][2] === 'fecha'));
        foreach (self::PERIODOS as $op => $frases) {
            if ($fecha && self::contiene($t, $frases)) {
                $filtros[] = ['c' => $fecha, 'o' => $op, 'v' => '', 'w' => ''];
                foreach ($frases as $x) {
                    $t = str_replace($x, ' ', $t);   // "últimos 7 días" no debe leerse como número
                }
                break;
            }
        }

        // Comparación numérica: campo nombrado antes del número o el principal del reporte
        if (preg_match('/entre (\d+(?:[.,]\d+)?) y (\d+(?:[.,]\d+)?)/', $t, $m, PREG_OFFSET_CAPTURE)) {
            $filtros[] = ['c' => self::campoNumerico(substr($t, 0, (int) $m[0][1]), $campos), 'o' => 'entre', 'v' => $m[1][0], 'w' => $m[2][0]];
        } else {
            foreach (self::COMPARACIONES as $op => $frases) {
                $alternativas = implode('|', array_map('preg_quote', $frases));
                if (preg_match("/\\b(?:$alternativas)\\b(?: a| que| de)?(?: bs)? (\\d+(?:[.,]\\d+)?)/", $t, $m, PREG_OFFSET_CAPTURE)) {
                    $filtros[] = ['c' => self::campoNumerico(substr($t, 0, (int) $m[0][1]), $campos), 'o' => $op, 'v' => $m[1][0], 'w' => ''];
                    break;
                }
            }
        }
        $filtros = array_values(array_filter($filtros, fn($f) => $f['c'] !== null));

        // Valores que existen en la base: un estado, una categoría, un proveedor, un método de pago…
        foreach ($opciones($tipo) as $campo => $valores) {
            usort($valores, fn($a, $b) => strlen((string) $b) <=> strlen((string) $a));   // primero el más específico
            foreach ($valores as $v) {
                $n = self::normalizar(str_replace('_', ' ', (string) $v));
                $legible = self::normalizar(Reporte::etiqueta((string) $v));
                if (strlen($n) >= 3 && (self::palabra($t, $n) || self::palabra($t, $legible))) {
                    $filtros[] = ['c' => $campo, 'o' => 'igual', 'v' => (string) $v, 'w' => ''];
                    break;
                }
            }
        }

        $formato = match (true) {
            self::contiene($t, ['pdf'])              => 'pdf',
            self::contiene($t, ['excel', 'xlsx'])    => 'xlsx',
            self::contiene($t, ['csv'])              => 'csv',
            default                                  => '',
        };
        $agrupar = '';
        if (preg_match('/(?:agrupad[oa]s?|por cada|agrupar)(?: por)? (.+)$/', $t, $m)) {
            foreach ($campos as $k => $c) {
                if (self::palabra($m[1], self::normalizar($c[0]))) {
                    $agrupar = $k;
                    break;
                }
            }
        }
        return ['consulta' => ['tipo' => $tipo, 'f' => $filtros, 'agrupar' => $agrupar], 'formato' => $formato];
    }

    /** Último campo numérico nombrado en el texto; si ninguno, el total o el primero que se suma. */
    private static function campoNumerico(string $antes, array $campos): ?string
    {
        $mejor = null;
        $posicion = -1;
        $porDefecto = null;
        foreach ($campos as $k => $c) {
            if (!in_array($c[2], ['numero', 'dinero'], true)) {
                continue;
            }
            if ($porDefecto === null || (empty($campos[$porDefecto][3]) && !empty($c[3]))) {
                $porDefecto = $k;
            }
            $p = strrpos($antes, self::normalizar($c[0]));
            if ($p !== false && $p >= $posicion) {
                $posicion = $p;
                $mejor = $k;
            }
        }
        return $mejor ?? (isset($campos['total']) ? 'total' : $porDefecto);
    }

    private static function contiene(string $t, array $frases): bool
    {
        foreach ($frases as $f) {
            if (preg_match('/\b' . preg_quote($f, '/') . '/', $t)) {
                return true;
            }
        }
        return false;
    }

    /** Palabra completa, admite el plural. */
    private static function palabra(string $t, string $valor): bool
    {
        return $valor !== '' && (bool) preg_match('/\b' . preg_quote($valor, '/') . '(?:s|es)?\b/', $t);
    }
}
