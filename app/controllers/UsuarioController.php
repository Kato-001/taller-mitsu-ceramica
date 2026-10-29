<?php
/**
 * CU03 · Gestionar usuarios (registrar, editar, desactivar).
 * Las cuentas de maestras y alumnos se crean desde sus propios módulos.
 */

declare(strict_types=1);

final class UsuarioController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('usuarios.gestionar');
        $f = ['q' => entrada('q'), 'rol' => entrada('rol'), 'estado' => entrada('estado')];
        if (!in_array($f['estado'], ['', 'ACTIVO', 'INACTIVO'], true)) {
            $f['estado'] = '';
        }
        $pag = new Paginador(Usuario::contar($f));
        $this->vista('P1_Seguridad/usuarios/index', [
            'usuarios' => Usuario::listar($f, $pag->porPagina, $pag->offset),
            'roles'    => Rol::todos(),
            'conteo'   => Usuario::conteoPorEstado($f),
            'f'        => $f,
            'pag'      => $pag,
        ]);
    }

    public function crear(): void
    {
        Auth::requerir('usuarios.gestionar');
        $this->vista('P1_Seguridad/usuarios/form', ['usuario' => null, 'roles' => self::rolesAsignables()]);
    }

    public function editar(): void
    {
        Auth::requerir('usuarios.gestionar');
        $u = Usuario::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P1_Seguridad/usuarios/form', ['usuario' => $u, 'roles' => Rol::todos(true)]);
    }

    public function guardar(): void
    {
        Auth::requerir('usuarios.gestionar');
        $id = entrada_int('id');
        $actual = $id ? (Usuario::buscar($id) ?? $this->noEncontrado()) : null;
        $d = [
            'nombre' => entrada('nombre'), 'apellido' => entrada('apellido'), 'correo' => mb_strtolower(entrada('correo')),
            'telefono' => entrada('telefono'), 'rol_id' => entrada('rol_id'),
            'password' => $_POST['password'] ?? '', 'password_confirmacion' => $_POST['password_confirmacion'] ?? '',
        ];
        // El rol de maestras y alumnos está ligado a su ficha; el propio usuario no cambia su rol
        if ($actual && ($actual['maestra_id'] || $actual['alumno_id'] || (int) $actual['id'] === Auth::id())) {
            $d['rol_id'] = (string) $actual['rol_id'];
        }
        $volver = $id ? url('/usuarios/editar', ['id' => $id]) : '/usuarios/nuevo';

        $rolesValidos = array_column($id ? Rol::todos(true) : self::rolesAsignables(), 'id');
        $v = self::validadorPersona($d)
            ->requerido('rol_id', 'El rol')->en('rol_id', array_map('strval', $rolesValidos), 'Rol no válido.')
            ->password('password', !$id)->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.')
            ->si(Usuario::existeCorreo($d['correo'], $id), 'correo', 'Ese correo ya está registrado.');
        $this->validar($v, $volver, $d);

        $ok = $this->intentar(function () use ($id, $d) {
            if ($id) {
                Usuario::actualizar($id, $d);
                Bitacora::registrar('EDITAR', 'SEGURIDAD', 'usuario', $id, "Usuario {$d['correo']} actualizado");
            } else {
                $nuevo = Usuario::crear($d);
                Bitacora::registrar('CREAR', 'SEGURIDAD', 'usuario', $nuevo, "Usuario {$d['correo']} creado");
            }
        }, $d);
        if (!$ok) {
            redirect($volver);
        }
        flash('ok', $id ? 'Usuario actualizado.' : 'Usuario creado. Ya puede iniciar sesión con su correo.');
        redirect('/usuarios');
    }

    public function estado(): void
    {
        Auth::requerir('usuarios.gestionar');
        $u = Usuario::buscar($this->id()) ?? $this->noEncontrado();
        if ((int) $u['id'] === Auth::id()) {
            flash('error', 'No puede desactivar su propia cuenta.');
            redirect('/usuarios');
        }
        $nuevo = $this->estadoContrario($u['estado']);
        if ($this->intentar(fn() => Usuario::cambiarEstado((int) $u['id'], $nuevo))) {
            Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'SEGURIDAD', 'usuario', (int) $u['id'], "Cuenta {$u['correo']}");
            flash('ok', $nuevo === 'ACTIVO' ? 'Cuenta activada.' : 'Cuenta desactivada: ya no puede iniciar sesión.');
        }
        volver('/usuarios');
    }

    /** Reglas comunes de nombre, apellido, correo y teléfono (también las usan maestras y alumnos). */
    public static function validadorPersona(array $d): Validator
    {
        return (new Validator($d))
            ->requerido('nombre', 'El nombre')->longitud('nombre', 2, 60)->regex('nombre', "/^[\\p{L} '.-]+$/u", 'El nombre solo admite letras.')
            ->requerido('apellido', 'El apellido')->longitud('apellido', 2, 60)->regex('apellido', "/^[\\p{L} '.-]+$/u", 'El apellido solo admite letras.')
            ->requerido('correo', 'El correo')->email('correo')->max('correo', 120)
            ->telefono('telefono');
    }

    /** Roles que se crean desde este módulo (maestras y alumnos tienen su propio formulario). */
    private static function rolesAsignables(): array
    {
        return array_values(array_filter(Rol::todos(true), fn($r) => !in_array($r['nombre'], ['MAESTRA', 'ALUMNO'], true)));
    }
}
