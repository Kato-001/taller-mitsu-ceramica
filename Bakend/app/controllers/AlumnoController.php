<?php
/**
 * CU12 · Gestionar alumnos (la recepción los registra; también se registran solos en el portal).
 */

declare(strict_types=1);

final class AlumnoController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('alumnos.gestionar');
        $f = ['q' => entrada('q'), 'nivel' => entrada('nivel'), 'estado' => entrada('estado')];
        $pag = new Paginador(Alumno::contar($f));
        $this->vista('P2_Clases/alumnos/index', ['alumnos' => Alumno::listar($f, $pag->porPagina, $pag->offset), 'f' => $f, 'pag' => $pag]);
    }

    public function ver(): void
    {
        Auth::requerir('alumnos.gestionar');
        $a = Alumno::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P2_Clases/alumnos/ver', [
            'alumno'   => $a,
            'proximas' => Sesion::reservasDeAlumno((int) $a['id'], true),
            'historial' => Sesion::reservasDeAlumno((int) $a['id'], false),
            'disponibles' => Sesion::disponibles(null, (int) $a['id'], 21),
        ]);
    }

    public function crear(): void
    {
        Auth::requerir('alumnos.gestionar');
        $this->vista('P2_Clases/alumnos/form', ['alumno' => null]);
    }

    public function editar(): void
    {
        Auth::requerir('alumnos.gestionar');
        $this->vista('P2_Clases/alumnos/form', ['alumno' => Alumno::buscar($this->id()) ?? $this->noEncontrado()]);
    }

    public function guardar(): void
    {
        Auth::requerir('alumnos.gestionar');
        $id = entrada_int('id');
        $actual = $id ? (Alumno::buscar($id) ?? $this->noEncontrado()) : null;
        $d = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
        $d['correo'] = mb_strtolower($d['correo'] ?? '');
        $volver = $id ? url('/alumnos/editar', ['id' => $id]) : '/alumnos/nuevo';
        $v = self::validador($d, $actual)
            ->requerido('nivel', 'El nivel')
            ->password('password', !$id)->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.');
        $this->validar($v, $volver, $d);

        $ok = $this->intentar(function () use ($id, $d, &$nuevo) {
            if ($id) {
                Alumno::actualizar($id, $d);
                if (($d['password'] ?? '') !== '') {
                    Usuario::cambiarPassword((int) Alumno::buscar($id)['usuario_id'], $d['password']);
                }
                Bitacora::registrar('EDITAR', 'CLASES', 'alumno', $id, "Alumno {$d['nombre']} {$d['apellido']}");
            } else {
                $nuevo = Alumno::crear($d);
                Bitacora::registrar('CREAR', 'CLASES', 'alumno', $nuevo, "Alumno {$d['nombre']} {$d['apellido']}");
            }
        }, $d);
        if (!$ok) {
            redirect($volver);
        }
        flash('ok', $id ? 'Datos del alumno actualizados.' : 'Alumno registrado. Ya puede reservarle clases.');
        redirect(url('/alumnos/ver', ['id' => $id ?: $nuevo]));
    }

    public function estado(): void
    {
        Auth::requerir('alumnos.gestionar');
        $a = Alumno::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($a['estado']);
        if ($this->intentar(fn() => Alumno::cambiarEstado((int) $a['id'], $nuevo))) {
            Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'CLASES', 'alumno', (int) $a['id'], "Alumno {$a['nombre_completo']}");
            flash('ok', $nuevo === 'ACTIVO' ? 'Alumno activado.' : 'Alumno desactivado: ya no puede reservar.');
        }
        volver('/alumnos');
    }

    /** Reglas del alumno (las usan la recepción, el registro público y el perfil del portal). */
    public static function validador(array $d, ?array $actual): Validator
    {
        $ci = (string) ($d['ci'] ?? '');
        return UsuarioController::validadorPersona($d)
            ->regex('ci', '/^\d{5,12}(-[0-9A-Z]{1,3})?$/', 'CI no válido (5 a 12 dígitos, complemento opcional).')
            ->fecha('fecha_nacimiento', '1920-01-01', date('Y-m-d', strtotime('-4 years')))
            ->en('nivel', array_keys(array_diff_key(NIVELES, ['TODOS' => 1])), 'Nivel no válido.')
            ->si(Usuario::existeCorreo((string) ($d['correo'] ?? ''), $actual['usuario_id'] ?? null), 'correo', 'Ese correo ya está registrado.')
            ->si($ci !== '' && Alumno::existeCi($ci, $actual['id'] ?? null), 'ci', 'Ya existe un alumno con ese CI.');
    }
}
