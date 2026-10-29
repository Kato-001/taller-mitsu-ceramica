<?php
/**
 * CU04 · Gestionar roles y permisos.
 */

declare(strict_types=1);

final class RolController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('roles.gestionar');
        $permisos = [];
        foreach (Rol::permisos() as $p) {
            $permisos[$p['modulo']][] = $p;
        }
        $this->vista('P1_Seguridad/roles/index', [
            'roles'    => Rol::todos(),
            'permisos' => $permisos,
            'matriz'   => Rol::matrizPermisos(),
        ]);
    }

    public function guardar(): void
    {
        Auth::requerir('roles.gestionar');
        $id = entrada_int('id');
        $nombre = mb_strtoupper(preg_replace('/\s+/', '_', entrada('nombre')));
        $descripcion = entrada('descripcion');

        if ($id) {
            $rol = Rol::buscar($id) ?? $this->noEncontrado();
            if (mb_strlen($descripcion) > 200) {
                flash('error', 'La descripción admite hasta 200 caracteres.');
                redirect('/roles');
            }
            Rol::actualizarDescripcion($id, $descripcion ?: null);
            Bitacora::registrar('EDITAR', 'SEGURIDAD', 'rol', $id, "Rol {$rol['nombre']}: descripción actualizada");
            flash('ok', 'Descripción del rol actualizada.');
            redirect('/roles');
        }

        $v = new Validator(['nombre' => $nombre, 'descripcion' => $descripcion]);
        $v->requerido('nombre', 'El nombre')->regex('nombre', '/^[A-ZÁÉÍÓÚÑ_]{3,40}$/u', 'Solo letras y guiones bajos (3 a 40).')->max('descripcion', 200);
        $this->validar($v, '/roles', $_POST);
        if ($this->intentar(function () use ($nombre, $descripcion) {
            $id = Rol::crear($nombre, $descripcion ?: null);
            Rol::asignarPermisos($id, [(int) Database::valor("select id from permiso where codigo = 'panel.ver'")]);
            Bitacora::registrar('CREAR', 'SEGURIDAD', 'rol', $id, "Rol $nombre creado");
        }, $_POST)) {
            flash('ok', "Rol $nombre creado. Marque sus permisos en la tabla.");
        }
        redirect('/roles');
    }

    public function permisos(): void
    {
        Auth::requerir('roles.gestionar');
        $rol = Rol::buscar($this->id('rol_id')) ?? $this->noEncontrado();
        $ids = array_map('intval', (array) ($_POST['permisos'] ?? []));

        // El administrador conserva siempre la gestión de usuarios y roles
        if ($rol['nombre'] === 'ADMINISTRADOR') {
            $fijos = array_column(Database::filas(
                'select id from permiso where codigo = any(cast(:c as text[]))',
                ['c' => '{' . implode(',', Rol::PERMISOS_ADMIN_FIJOS) . '}']
            ), 'id');
            $ids = array_merge($ids, array_map('intval', $fijos));
        }
        Rol::asignarPermisos((int) $rol['id'], $ids);
        Bitacora::registrar('PERMISOS', 'SEGURIDAD', 'rol', (int) $rol['id'], "Permisos del rol {$rol['nombre']} actualizados (" . count(array_unique($ids)) . ')');
        flash('ok', "Permisos del rol {$rol['nombre']} guardados.");
        redirect('/roles');
    }

    /** Los roles no se borran: se desactivan, y solo si no tienen usuarios activos. */
    public function estado(): void
    {
        Auth::requerir('roles.gestionar');
        $rol = Rol::buscar($this->id()) ?? $this->noEncontrado();
        if (in_array($rol['nombre'], Rol::DEL_SISTEMA, true)) {
            flash('error', 'Los roles del sistema no se pueden desactivar.');
            redirect('/roles');
        }
        $nuevo = $this->estadoContrario($rol['estado']);
        if ($nuevo === 'INACTIVO' && Database::valor("select 1 from usuario where rol_id = :r and estado = 'ACTIVO'", ['r' => $rol['id']])) {
            flash('error', 'El rol tiene usuarios activos. Cámbielos de rol primero.');
            redirect('/roles');
        }
        Rol::cambiarEstado((int) $rol['id'], $nuevo);
        Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'SEGURIDAD', 'rol', (int) $rol['id'], "Rol {$rol['nombre']}");
        flash('ok', "Rol {$rol['nombre']} " . ($nuevo === 'ACTIVO' ? 'activado.' : 'desactivado.'));
        redirect('/roles');
    }
}
