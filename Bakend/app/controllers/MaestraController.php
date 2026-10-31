<?php
/**
 * CU07 · Gestionar maestras (con su cuenta de acceso).
 */

declare(strict_types=1);

final class MaestraController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('maestras.gestionar');
        $this->vista('P2_Clases/maestras/index', ['maestras' => Maestra::todas()]);
    }

    public function crear(): void
    {
        Auth::requerir('maestras.gestionar');
        $this->vista('P2_Clases/maestras/form', ['maestra' => null]);
    }

    public function editar(): void
    {
        Auth::requerir('maestras.gestionar');
        $this->vista('P2_Clases/maestras/form', ['maestra' => Maestra::buscar($this->id()) ?? $this->noEncontrado()]);
    }

    public function guardar(): void
    {
        Auth::requerir('maestras.gestionar');
        $id = entrada_int('id');
        $actual = $id ? (Maestra::buscar($id) ?? $this->noEncontrado()) : null;
        $d = ['nombre' => entrada('nombre'), 'apellido' => entrada('apellido'), 'correo' => mb_strtolower(entrada('correo')),
              'telefono' => entrada('telefono'), 'especialidad' => entrada('especialidad'), 'biografia' => entrada('biografia'),
              'password' => $_POST['password'] ?? '', 'password_confirmacion' => $_POST['password_confirmacion'] ?? ''];
        $volver = $id ? url('/maestras/editar', ['id' => $id]) : '/maestras/nueva';
        $v = UsuarioController::validadorPersona($d)
            ->requerido('especialidad', 'La especialidad')->longitud('especialidad', 3, 120)->max('biografia', 500)
            ->password('password', !$id)->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.')
            ->si(Usuario::existeCorreo($d['correo'], $actual['usuario_id'] ?? null), 'correo', 'Ese correo ya está registrado.');
        $this->validar($v, $volver, $d);

        $ok = $this->intentar(function () use ($id, $d) {
            if ($id) {
                Maestra::actualizar($id, $d);
                Bitacora::registrar('EDITAR', 'CLASES', 'maestra', $id, "Maestra {$d['nombre']} {$d['apellido']}");
            } else {
                $nuevo = Maestra::crear($d);
                Bitacora::registrar('CREAR', 'CLASES', 'maestra', $nuevo, "Maestra {$d['nombre']} {$d['apellido']}");
            }
        }, $d);
        if (!$ok) {
            redirect($volver);
        }
        flash('ok', $id ? 'Datos de la maestra actualizados.' : 'Maestra registrada. Ya puede ingresar con su correo.');
        redirect('/maestras');
    }

    public function estado(): void
    {
        Auth::requerir('maestras.gestionar');
        $m = Maestra::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($m['estado']);
        if ($this->intentar(fn() => Maestra::cambiarEstado((int) $m['id'], $nuevo))) {
            Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'CLASES', 'maestra', (int) $m['id'], "Maestra {$m['nombre_completo']}");
            flash('ok', $nuevo === 'ACTIVO' ? 'Maestra activada.' : 'Maestra desactivada.');
        }
        redirect('/maestras');
    }
}
