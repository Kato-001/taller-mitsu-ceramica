<?php
/**
 * Ciclo IV · P5 Reportes y calidad del servicio
 * CU23 Consultar con filtros · CU24 Exportar reportes · CU25 Comando de voz.
 */

declare(strict_types=1);

final class ReporteController extends Controlador
{
    /** CU23 · Arma y muestra la consulta elegida. */
    public function index(): void
    {
        $u = Auth::requerir('reportes.ver');
        $q = Reporte::normalizar($_GET);
        $error = null;
        $r = null;
        try {
            $r = Reporte::ejecutar($q);
        } catch (AppException $e) {
            $error = $e->getMessage();
        }
        $pag = new Paginador($r ? count($r['filas']) : 0, 20);
        $this->vista('P5_Reportes/reportes', [
            'tipos'     => Reporte::permitidos(),
            'q'         => $q,
            'opciones'  => Reporte::opciones($q['tipo']),
            'r'         => $r,
            'error'     => $error,
            'frase'     => entrada('frase'),
            'guardados' => Reporte::guardados((int) $u['id']),
            'pag'       => $pag,
        ]);
    }

    /** CU24 · Descarga el reporte en PDF, Excel o CSV. */
    public function exportar(): void
    {
        $u = Auth::requerir('reportes.ver');
        $formato = entrada('formato');
        if (!isset(Exportador::FORMATOS[$formato])) {
            $this->noEncontrado();
        }
        $q = Reporte::normalizar($_GET);
        try {
            $r = Reporte::ejecutar($q);
        } catch (AppException $e) {
            flash('error', $e->getMessage());
            redirect(url('/reportes', $q));
        }
        $archivo = Exportador::generar($formato, $r, $u['nombre_completo']);
        Bitacora::registrar('EXPORTAR', 'REPORTES', null, null, "Reporte {$r['titulo']} en " . Exportador::FORMATOS[$formato][0] . ': ' . implode(' · ', $r['descripcion']));
        header('Content-Type: ' . Exportador::FORMATOS[$formato][1]);
        header('Content-Disposition: attachment; filename="reporte_' . $q['tipo'] . '_' . date('Ymd_His') . '.' . $formato . '"');
        header('Content-Length: ' . strlen($archivo));
        header('Cache-Control: no-store');
        echo $archivo;
    }

    /** CU25 · Convierte la frase dictada en una consulta. */
    public function voz(): void
    {
        Auth::requerir('reportes.ver');
        $frase = mb_substr(entrada('frase'), 0, 200);
        if ($frase === '') {
            flash('error', 'Diga o escriba qué desea consultar.');
            redirect('/reportes');
        }
        try {
            $c = ComandoVoz::interpretar($frase, Reporte::permitidos(), fn(string $t) => Reporte::opciones($t));
        } catch (AppException $e) {
            flash('error', $e->getMessage());
            redirect(url('/reportes', ['frase' => $frase]));
        }
        Bitacora::registrar('COMANDO_VOZ', 'REPORTES', null, null, "“{$frase}”");
        redirect($c['formato'] !== ''
            ? url('/reportes/exportar', $c['consulta'] + ['formato' => $c['formato']])
            : url('/reportes', $c['consulta'] + ['frase' => $frase]));
    }

    /** CU23 · Guarda la consulta con un nombre para volver a usarla. */
    public function guardar(): void
    {
        $u = Auth::requerir('reportes.ver');
        parse_str(entrada('consulta'), $datos);
        $q = Reporte::normalizar($datos);
        $nombre = entrada('nombre');
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 80) {
            flash('error', 'Escriba un nombre de 3 a 80 caracteres para el reporte.');
        } elseif ($this->intentar(fn() => Reporte::guardar((int) $u['id'], $nombre, $q))) {
            Bitacora::registrar('CREAR', 'REPORTES', 'reporte_guardado', null, "Guardó el reporte “{$nombre}”");
            flash('ok', "Reporte “{$nombre}” guardado.");
        }
        redirect(url('/reportes', $q));
    }

    public function eliminar(): void
    {
        $u = Auth::requerir('reportes.ver');
        Reporte::eliminar($this->id(), (int) $u['id']);
        flash('ok', 'Reporte guardado eliminado.');
        redirect('/reportes');
    }
}
