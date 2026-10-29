<?php
/**
 * Portal del alumno · CU10 (reservar y cancelar sus clases) y CU12 (su perfil).
 */

declare(strict_types=1);

final class PortalController extends Controlador
{
    public function inicio(): void
    {
        $u = Auth::requerir('portal.alumno');
        $a = Alumno::buscar((int) $u['alumno_id']) ?? $this->noEncontrado();
        $this->vista('P2_Clases/portal/inicio', [
            'alumno'   => $a,
            'proximas' => Sesion::reservasDeAlumno((int) $a['id'], true),
            'historial' => array_slice(Sesion::reservasDeAlumno((int) $a['id'], false), 0, 6),
        ]);
    }

    public function reservar(): void
    {
        $u = Auth::requerir('portal.alumno');
        $taller = entrada_int('taller');
        $this->vista('P2_Clases/portal/reservar', [
            'sesiones' => Sesion::disponibles($taller, (int) $u['alumno_id']),
            'talleres' => Taller::todos(true),
            'taller'   => $taller,
        ]);
    }

    public function guardarReserva(): void
    {
        $u = Auth::requerir('portal.alumno');
        $s = Sesion::buscar($this->id('sesion_id')) ?? $this->noEncontrado();
        if ($this->intentar(fn() => Sesion::reservar((int) $s['id'], (int) $u['alumno_id']))) {
            Bitacora::registrar('RESERVAR', 'CLASES', 'reserva', (int) $s['id'], "Reserva desde el portal: {$s['taller']} " . fecha($s['fecha']));
            flash('ok', "¡Listo! Reservaste {$s['taller']} el " . fecha_larga($s['fecha']) . ' a las ' . hora($s['hora_inicio']) . '.');
            redirect('/portal');
        }
        redirect('/portal/reservar');
    }

    public function cancelar(): void
    {
        $u = Auth::requerir('portal.alumno');
        $r = Sesion::reserva($this->id()) ?? $this->noEncontrado();
        if ((int) $r['alumno_id'] !== (int) $u['alumno_id']) {
            $this->noEncontrado();
        }
        $horas = (int) config('HORAS_CANCELACION', '12');
        if (strtotime($r['fecha'] . ' ' . $r['hora_inicio']) - time() < $horas * 3600) {
            flash('error', "Solo puedes cancelar hasta $horas horas antes de la clase. Comunícate con el taller.");
            redirect('/portal');
        }
        if ($this->intentar(fn() => Sesion::cancelarReserva((int) $r['id'], 'Cancelada por el alumno desde el portal'))) {
            Bitacora::registrar('CANCELAR', 'CLASES', 'reserva', (int) $r['id'], "El alumno canceló {$r['taller']} del " . fecha($r['fecha']));
            flash('ok', 'Reserva cancelada. Tu cupo quedó libre para otra persona.');
        }
        redirect('/portal');
    }

    public function perfil(): void
    {
        $u = Auth::requerir('portal.alumno');
        $this->vista('P2_Clases/portal/perfil', ['alumno' => Alumno::buscar((int) $u['alumno_id'])]);
    }

    public function guardarPerfil(): void
    {
        $u = Auth::requerir('portal.alumno');
        $a = Alumno::buscar((int) $u['alumno_id']) ?? $this->noEncontrado();
        $d = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
        $d['correo'] = mb_strtolower($d['correo'] ?? '');
        $d['nivel'] = $a['nivel'];   // el nivel lo asigna la maestra o la recepción
        $this->validar(AlumnoController::validador($d, $a), '/portal/perfil', $d);
        if ($this->intentar(fn() => Alumno::actualizar((int) $a['id'], $d, false), $d)) {
            Bitacora::registrar('EDITAR', 'CLASES', 'alumno', (int) $a['id'], 'El alumno actualizó su perfil');
            flash('ok', 'Tus datos fueron actualizados.');
        }
        redirect('/portal/perfil');
    }
}
