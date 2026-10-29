<?php
/**
 * CU09 · Calendario de clases · CU10 · Reservar clase (recepción) · CU11 · Registrar asistencia.
 */

declare(strict_types=1);

final class ClaseController extends Controlador
{
    /** Calendario semanal. La maestra ve solo sus clases. */
    public function calendario(): void
    {
        $u = Auth::requerir('sesiones.gestionar', 'reservas.gestionar', 'asistencia.registrar', 'clases.propias');
        $base = entrada('semana');
        $base = preg_match('/^\d{4}-\d{2}-\d{2}$/', $base) && strtotime($base) ? $base : hoy();
        $lunes = date('Y-m-d', strtotime('monday this week', strtotime($base)));
        $domingo = date('Y-m-d', strtotime($lunes . ' +6 days'));
        $soloPropias = !Auth::puede('sesiones.gestionar', 'reservas.gestionar', 'asistencia.registrar');
        $taller = entrada_int('taller');
        $maestra = $soloPropias ? (int) $u['maestra_id'] : entrada_int('maestra');

        $this->vista('P2_Clases/calendario', [
            'lunes'    => $lunes,
            'sesiones' => Sesion::entre($lunes, $domingo, $maestra, $taller),
            'talleres' => Taller::todos(true),
            'maestras' => $soloPropias ? [] : Maestra::activas(),
            'taller'   => $taller,
            'maestra'  => $maestra,
            'propias'  => $soloPropias,
        ]);
    }

    public function ver(): void
    {
        $u = Auth::requerir('sesiones.gestionar', 'reservas.gestionar', 'asistencia.registrar', 'clases.propias');
        $s = Sesion::buscar($this->id()) ?? $this->noEncontrado();
        $this->exigirAcceso($s, $u);
        $this->vista('P2_Clases/sesion', [
            'sesion'   => $s,
            'reservas' => Sesion::reservas((int) $s['id']),
            'alumnos'  => Auth::puede('reservas.gestionar') ? Alumno::activos() : [],
            'maestras' => Auth::puede('sesiones.gestionar') ? Maestra::activas() : [],
        ]);
    }

    public function generar(): void
    {
        Auth::requerir('sesiones.gestionar');
        $semanas = max(1, min(12, (int) config('SEMANAS_CALENDARIO', '4')));
        $hasta = date('Y-m-d', strtotime("+$semanas weeks"));
        if ($this->intentar(function () use ($hasta, &$n) {
            $n = Sesion::generar(hoy(), $hasta);
        })) {
            Bitacora::registrar('GENERAR', 'CLASES', 'sesion_clase', null, "Clases generadas hasta $hasta: $n");
            flash($n ? 'ok' : 'info', $n ? "Se generaron $n clases hasta el " . fecha($hasta) . '.' : 'El calendario ya estaba completo hasta el ' . fecha($hasta) . '.');
        }
        volver('/clases');
    }

    public function cancelar(): void
    {
        Auth::requerir('sesiones.gestionar');
        $s = Sesion::buscar($this->id()) ?? $this->noEncontrado();
        $motivo = entrada('motivo');
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 200) {
            flash('error', 'Escriba el motivo de la cancelación (5 a 200 caracteres).');
            redirect(url('/clases/ver', ['id' => $s['id']]));
        }
        if ($this->intentar(fn() => Sesion::cancelar((int) $s['id'], $motivo))) {
            Bitacora::registrar('CANCELAR', 'CLASES', 'sesion_clase', (int) $s['id'], "{$s['taller']} del " . fecha($s['fecha']) . ": $motivo");
            flash('ok', 'Clase cancelada. Sus reservas pendientes también se cancelaron.');
        }
        redirect(url('/clases/ver', ['id' => $s['id']]));
    }

    public function maestra(): void
    {
        Auth::requerir('sesiones.gestionar');
        $s = Sesion::buscar($this->id()) ?? $this->noEncontrado();
        $m = Maestra::buscar((int) entrada_int('maestra_id')) ?? $this->noEncontrado();
        if ($this->intentar(fn() => Sesion::cambiarMaestra((int) $s['id'], (int) $m['id']))) {
            Bitacora::registrar('REEMPLAZO', 'CLASES', 'sesion_clase', (int) $s['id'], "Clase del " . fecha($s['fecha']) . " a cargo de {$m['nombre_completo']}");
            flash('ok', "La clase quedó a cargo de {$m['nombre_completo']}.");
        }
        redirect(url('/clases/ver', ['id' => $s['id']]));
    }

    /** CU10 · La recepción reserva una clase para un alumno. */
    public function reservar(): void
    {
        Auth::requerir('reservas.gestionar');
        $sesionId = $this->id('sesion_id');
        $alumnoId = $this->id('alumno_id');
        if ($this->intentar(fn() => Sesion::reservar($sesionId, $alumnoId))) {
            $a = Alumno::buscar($alumnoId);
            Bitacora::registrar('RESERVAR', 'CLASES', 'reserva', $sesionId, "Reserva para {$a['nombre_completo']}");
            flash('ok', "Reserva registrada para {$a['nombre_completo']}.");
        }
        volver(url('/clases/ver', ['id' => $sesionId]));
    }

    public function cancelarReserva(): void
    {
        Auth::requerir('reservas.gestionar');
        $r = Sesion::reserva($this->id()) ?? $this->noEncontrado();
        $motivo = entrada('motivo') ?: 'Cancelada por recepción';
        if ($this->intentar(fn() => Sesion::cancelarReserva((int) $r['id'], mb_substr($motivo, 0, 200)))) {
            Bitacora::registrar('CANCELAR', 'CLASES', 'reserva', (int) $r['id'], "Reserva de {$r['taller']} del " . fecha($r['fecha']) . ": $motivo");
            flash('ok', 'Reserva cancelada: el cupo quedó libre.');
        }
        volver('/clases');
    }

    /** CU11 · Registrar asistencia de toda la clase. */
    public function asistencia(): void
    {
        $u = Auth::requerir('asistencia.registrar', 'clases.propias');
        $s = Sesion::buscar($this->id('sesion_id')) ?? $this->noEncontrado();
        $this->exigirAcceso($s, $u);
        if ($s['fecha'] > hoy()) {
            flash('error', 'La asistencia se registra el día de la clase o después.');
            redirect(url('/clases/ver', ['id' => $s['id']]));
        }
        $marcas = array_filter((array) ($_POST['asistencia'] ?? []), fn($v) => in_array($v, ['ASISTIO', 'FALTO'], true));
        if (!$marcas) {
            flash('error', 'Marque la asistencia de al menos un alumno.');
            redirect(url('/clases/ver', ['id' => $s['id']]));
        }
        if ($this->intentar(function () use ($s, $marcas, &$n) {
            $n = Sesion::registrarAsistencia((int) $s['id'], $marcas);
        })) {
            $presentes = count(array_filter($marcas, fn($v) => $v === 'ASISTIO'));
            Bitacora::registrar('ASISTENCIA', 'CLASES', 'sesion_clase', (int) $s['id'], "{$s['taller']} del " . fecha($s['fecha']) . ": $presentes presentes de " . count($marcas));
            flash('ok', "Asistencia guardada: $presentes presente(s) de " . count($marcas) . '.');
        }
        redirect(url('/clases/ver', ['id' => $s['id']]));
    }

    /** La maestra solo accede a sus propias clases. */
    private function exigirAcceso(array $s, array $u): void
    {
        if (!Auth::puede('sesiones.gestionar', 'reservas.gestionar', 'asistencia.registrar') && (int) $s['maestra_id'] !== (int) $u['maestra_id']) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }
}
