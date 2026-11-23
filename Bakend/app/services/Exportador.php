<?php
/**
 * CU24 · Convierte el resultado de un reporte a PDF, Excel (.xlsx) o CSV.
 * Está escrito con funciones propias de PHP: no usa bibliotecas externas.
 */

declare(strict_types=1);

final class Exportador
{
    public const FORMATOS = [
        'pdf'  => ['PDF', 'application/pdf'],
        'xlsx' => ['Excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv'  => ['CSV', 'text/csv; charset=utf-8'],
    ];

    /** Contenido del archivo en el formato pedido. */
    public static function generar(string $formato, array $r, string $autor): string
    {
        return match ($formato) {
            'pdf'  => self::pdf($r, $autor),
            'xlsx' => self::xlsx($r, $autor),
            default => self::csv($r),
        };
    }

    // ------------------------------------------------------------------ CSV

    public static function csv(array $r): string
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");   // para que Excel reconozca las tildes
        fputcsv($f, array_column($r['columnas'], 0), ';', '"', '');
        foreach ($r['filas'] as $fila) {
            fputcsv($f, array_map(fn($k) => self::celdaCsv($fila[$k] ?? null, $r['columnas'][$k][1]), array_keys($r['columnas'])), ';', '"', '');
        }
        rewind($f);
        return (string) stream_get_contents($f);
    }

    private static function celdaCsv(mixed $v, string $tipo): string
    {
        if ($v === null) {
            return '';
        }
        if (in_array($tipo, ['numero', 'dinero'], true)) {
            return str_replace('.', ',', (string) $v);
        }
        $s = Reporte::mostrar($v, $tipo);
        return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;   // evita que Excel lo ejecute como fórmula
    }

    // ---------------------------------------------------------------- Excel

    public static function xlsx(array $r, string $autor): string
    {
        $x = fn($s) => htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $texto = fn(string $ref, $v, int $estilo = 0) => '<c r="' . $ref . '" s="' . $estilo . '" t="inlineStr"><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
        $numero = fn(string $ref, $v, int $estilo) => '<c r="' . $ref . '" s="' . $estilo . '"><v>' . (float) $v . '</v></c>';
        $claves = array_keys($r['columnas']);
        $filas = [
            '<row r="1">' . $texto('A1', config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü') . ' · ' . $r['titulo'], 2) . '</row>',
            '<row r="2">' . $texto('A2', implode(' · ', $r['descripcion'])) . '</row>',
            '<row r="3">' . $texto('A3', 'Generado el ' . date('d/m/Y H:i') . ' por ' . $autor . ' · ' . $r['total'] . ' registro(s)') . '</row>',
        ];
        $n = 5;
        $c = '';
        foreach ($claves as $i => $k) {
            $c .= $texto(self::letra($i) . $n, $r['columnas'][$k][0], 1);
        }
        $filas[] = "<row r=\"$n\">$c</row>";
        $anchos = array_map(fn($k) => mb_strlen($r['columnas'][$k][0]) + 3, $claves);
        $poner = function (array $fila, bool $total) use (&$n, &$filas, &$anchos, $claves, $r, $texto, $numero): void {
            $n++;
            $c = '';
            foreach ($claves as $i => $k) {
                $v = $fila[$k] ?? null;
                $tipo = $r['columnas'][$k][1];
                $ref = self::letra($i) . $n;
                if ($v === null || $v === '') {
                    continue;
                }
                $mostrado = $total && !is_numeric($v) ? (string) $v : Reporte::mostrar($v, $tipo);
                $anchos[$i] = min(45, max($anchos[$i], mb_strlen($mostrado) + 3));
                $esNumero = in_array($tipo, ['numero', 'dinero'], true) && is_numeric($v);
                $c .= $esNumero ? $numero($ref, $v, $total ? 5 : ($tipo === 'dinero' ? 4 : 3)) : $texto($ref, $total ? $v : $mostrado, $total ? 1 : 0);
            }
            $filas[] = "<row r=\"$n\">$c</row>";
        };
        foreach ($r['filas'] as $fila) {
            $poner($fila, false);
        }
        $ultima = $n;
        if ($r['totales'] && $r['filas']) {
            $t = $r['totales'];
            if (!isset($t[$claves[0]])) {
                $t[$claves[0]] = 'TOTAL';
            }
            $poner($t, true);
        }
        $cols = '';
        foreach ($anchos as $i => $a) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $a . '" customWidth="1"/>';
        }
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $cab = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        return self::zip([
            '[Content_Types].xml' => $cab . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => $cab . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => $cab . '<workbook xmlns="' . $ns . '" xmlns:r="' . $rel . '"><sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $cab . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $rel . '/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="' . $rel . '/styles" Target="styles.xml"/></Relationships>',
            // Estilos: 0 normal · 1 encabezado · 2 título · 3 número · 4 dinero · 5 total
            'xl/styles.xml' => $cab . '<styleSheet xmlns="' . $ns . '"><numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Bs &quot;#,##0.00"/></numFmts><fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF9C5B3A"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF9C5B3A"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF7EDE2"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="6"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf fontId="2" applyFont="1"/><xf numFmtId="4" applyNumberFormat="1"/><xf numFmtId="164" applyNumberFormat="1"/><xf numFmtId="4" fontId="3" fillId="3" applyFont="1" applyFill="1" applyNumberFormat="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => $cab . '<worksheet xmlns="' . $ns . '"><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . implode('', $filas) . '</sheetData><autoFilter ref="A5:' . self::letra(count($claves) - 1) . max(5, $ultima) . '"/></worksheet>',
        ]);
    }

    private static function letra(int $i): string
    {
        return $i < 26 ? chr(65 + $i) : chr(64 + intdiv($i, 26)) . chr(65 + $i % 26);
    }

    /** Arma un archivo ZIP en memoria (un .xlsx es un ZIP con XML adentro). */
    private static function zip(array $archivos): string
    {
        $datos = '';
        $central = '';
        [$fecha, $hora] = [((int) date('Y') - 1980) << 9 | (int) date('n') << 5 | (int) date('j'), (int) date('G') << 11 | (int) date('i') << 5];
        foreach ($archivos as $nombre => $contenido) {
            $z = (string) gzdeflate($contenido);
            $comun = pack('vvvvvVVVvv', 20, 0x0800, 8, $hora, $fecha, crc32($contenido), strlen($z), strlen($contenido), strlen($nombre), 0);
            $central .= "PK\x01\x02" . pack('v', 20) . $comun . pack('vvvVV', 0, 0, 0, 0, strlen($datos)) . $nombre;
            $datos .= "PK\x03\x04" . $comun . $nombre . $z;
        }
        return $datos . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($archivos), count($archivos), strlen($central), strlen($datos), 0);
    }

    // ------------------------------------------------------------------ PDF

    private const ANCHOS = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    /** Ancho en puntos de un texto Windows-1252 en Helvetica. */
    private static function medir(string $s, float $tam): float
    {
        $w = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $w += self::ANCHOS[ord($s[$i]) - 32] ?? 556;
        }
        return $w * $tam / 1000;
    }

    private static function latino(string $s): string
    {
        $s = strtr($s, ['≥' => '>=', '≤' => '<=', '≠' => '<>', '★' => '*', "\n" => ' ', "\r" => ' ', "\t" => ' ']);
        return (string) mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    /** Parte un texto en renglones que entren en el ancho dado. */
    private static function renglones(string $s, float $ancho, float $tam, int $maximo): array
    {
        $r = [];
        $linea = '';
        foreach (explode(' ', $s) as $palabra) {
            while (self::medir($palabra, $tam) > $ancho && strlen($palabra) > 1) {   // palabra más ancha que la columna
                $corte = strlen($palabra) - 1;
                while ($corte > 1 && self::medir(substr($palabra, 0, $corte), $tam) > $ancho) {
                    $corte--;
                }
                if ($linea !== '') {
                    $r[] = $linea;
                    $linea = '';
                }
                $r[] = substr($palabra, 0, $corte);
                $palabra = substr($palabra, $corte);
            }
            $prueba = $linea === '' ? $palabra : "$linea $palabra";
            if ($linea !== '' && self::medir($prueba, $tam) > $ancho) {
                $r[] = $linea;
                $linea = $palabra;
            } else {
                $linea = $prueba;
            }
        }
        $r[] = $linea;
        if (count($r) > $maximo) {
            $r = array_slice($r, 0, $maximo);
            $r[$maximo - 1] = substr($r[$maximo - 1], 0, -1) . "\x85";   // …
        }
        return $r;
    }

    public static function pdf(array $r, string $autor): string
    {
        [$W, $H, $M, $T] = [792.0, 612.0, 36.0, 8.0];   // carta horizontal, márgenes y tamaño de letra
        $util = $W - 2 * $M;
        $claves = array_keys($r['columnas']);
        $derecha = array_map(fn($k) => in_array($r['columnas'][$k][1], ['numero', 'dinero'], true), $claves);
        $celda = fn(array $fila, string $k) => self::latino(Reporte::mostrar($fila[$k] ?? null, $r['columnas'][$k][1]));

        // Ancho de columnas según el contenido, ajustado al ancho de la página
        $anchos = [];
        foreach ($claves as $i => $k) {
            $a = self::medir(self::latino($r['columnas'][$k][0]), $T) * 1.12;
            foreach (array_slice($r['filas'], 0, 300) as $fila) {
                $a = max($a, self::medir($celda($fila, $k), $T));
            }
            if (isset($r['totales'][$k])) {
                $a = max($a, self::medir($celda($r['totales'], $k), $T) * 1.12);
            }
            $anchos[$i] = min($a, 190) + 9;
        }
        $factor = $util / max(1, array_sum($anchos));
        $anchos = array_map(fn($a) => $a * $factor, $anchos);

        $paginas = [];
        $c = '';
        $y = 0.0;
        $esc = fn(string $s) => strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $escribir = function (float $x, float $yy, string $s, float $tam, string $fuente = 'F1', string $color = '0.17 0.13 0.11') use (&$c, $esc): void {
            $c .= sprintf("BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $fuente, $tam, $color, $x, $yy, $esc($s));
        };
        $caja = function (float $x, float $yy, float $w, float $h, string $color) use (&$c): void {
            $c .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $color, $x, $yy, $w, $h);
        };
        $encabezado = function () use (&$y, &$c, $M, $T, $util, $claves, $anchos, $derecha, $r, $escribir, $caja): void {
            $caja($M, $y - 16, $util, 16, '0.612 0.357 0.227');
            $x = $M;
            foreach ($claves as $i => $k) {
                $s = self::renglones(self::latino($r['columnas'][$k][0]), $anchos[$i] - 6, $T, 1)[0];
                $escribir($derecha[$i] ? $x + $anchos[$i] - 4 - self::medir($s, $T) * 1.06 : $x + 4, $y - 11, $s, $T, 'F2', '1 1 1');
                $x += $anchos[$i];
            }
            $y -= 16;
        };
        $nueva = function () use (&$paginas, &$c, &$y, $H, $M, $encabezado): void {
            if ($c !== '') {
                $paginas[] = $c;
            }
            $c = '';
            $y = $H - $M;
            if ($paginas) {
                $encabezado();
            }
        };
        $nueva();
        $escribir($M, $y - 14, self::latino(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü')), 10, 'F2', '0.612 0.357 0.227');
        $escribir($M, $y - 34, self::latino('Reporte: ' . $r['titulo']), 16, 'F2');
        $y -= 50;
        foreach (self::renglones(self::latino('Filtros: ' . implode(' · ', $r['descripcion'])), $util, 9, 4) as $linea) {
            $escribir($M, $y, $linea, 9);
            $y -= 12;
        }
        $escribir($M, $y, self::latino('Generado el ' . date('d/m/Y H:i') . ' por ' . $autor . ' · ' . $r['total'] . ' registro(s)'
            . ($r['total'] > count($r['filas']) ? ' (se muestran los primeros ' . count($r['filas']) . ')' : '')), 9, 'F1', '0.44 0.37 0.33');
        $y -= 14;
        $encabezado();

        $fila = function (array $datos, bool $par, bool $total) use (&$y, &$c, $M, $T, $util, $claves, $anchos, $derecha, $celda, $r, $escribir, $caja, $nueva): void {
            $partes = [];
            foreach ($claves as $i => $k) {
                $s = $total ? (isset($datos[$k]) ? $celda($datos, $k) : ($i === 0 ? 'TOTAL' : '')) : $celda($datos, $k);
                $partes[$i] = self::renglones($s, $anchos[$i] - 8, $T, 3);
            }
            $alto = max(array_map('count', $partes)) * 10 + 5;
            if ($y - $alto < $M + 18) {
                $nueva();
            }
            if ($total || $par) {
                $caja($M, $y - $alto, $util, $alto, $total ? '0.945 0.894 0.827' : '0.984 0.965 0.933');
            }
            $x = $M;
            foreach ($claves as $i => $k) {
                foreach ($partes[$i] as $j => $s) {
                    $escribir($derecha[$i] ? $x + $anchos[$i] - 4 - self::medir($s, $T) * ($total ? 1.06 : 1) : $x + 4, $y - 10 - $j * 10, $s, $T, $total ? 'F2' : 'F1');
                }
                $x += $anchos[$i];
            }
            $y -= $alto;
        };
        foreach ($r['filas'] as $n => $f) {
            $fila($f, $n % 2 === 1, false);
        }
        if (!$r['filas']) {
            $escribir($M + 4, $y - 16, 'No hay registros con esos filtros.', 10);
        } elseif ($r['totales']) {
            $fila($r['totales'], false, true);
        }
        $paginas[] = $c;

        // Objetos del archivo
        $n = count($paginas);
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Count ' . $n . ' /Kids [' . implode(' ', array_map(fn($i) => (5 + 2 * $i) . ' 0 R', range(0, $n - 1))) . '] >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        foreach ($paginas as $i => $contenido) {
            $c = $contenido;
            $pie = self::latino('Página ' . ($i + 1) . ' de ' . $n);
            $escribir($W - $M - self::medir($pie, 8), $M - 14, $pie, 8, 'F1', '0.44 0.37 0.33');
            $z = (string) gzcompress($c);
            $objetos[5 + 2 * $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $W $H] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . (6 + 2 * $i) . ' 0 R >>';
            $objetos[6 + 2 * $i] = '<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n$z\nendstream";
        }
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $tabla = '';
        foreach ($objetos as $i => $o) {
            $tabla .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= "$i 0 obj\n$o\nendobj\n";
        }
        $total = count($objetos) + 1;
        return $pdf . "xref\n0 $total\n0000000000 65535 f \n$tabla" . "trailer\n<< /Size $total /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";
    }
}
