<?php
/**
 * CU05 · Bitácora · CU06 · Configuración y catálogos · Mi cuenta.
 */

declare(strict_types=1);

final class SistemaController extends Controlador
{
    // ----------------------------------------------------------- Bitácora

    public function bitacora(): void
    {
        Auth::requerir('bitacora.ver');
        $f = ['q' => entrada('q'), 'accion' => entrada('accion'), 'modulo' => entrada('modulo'), 'desde' => entrada('desde'), 'hasta' => entrada('hasta')];
        foreach (['desde', 'hasta'] as $k) {
            if ($f[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
                $f[$k] = '';
            }
        }
        $pag = new Paginador(Bitacora::contar($f), 25);
        $this->vista('P1_Seguridad/bitacora', [
            'registros' => Bitacora::listar($f, $pag->porPagina, $pag->offset),
            'acciones'  => Bitacora::acciones(),
            'modulos'   => Bitacora::modulos(),
            'f'         => $f,
            'pag'       => $pag,
        ]);
    }

    // ------------------------------------------------------ Configuración

    public function configuracion(): void
    {
        Auth::requerir('configuracion.gestionar');
        $this->vista('P1_Seguridad/configuracion', [
            'parametros' => Configuracion::listar(),
            'categorias' => Configuracion::categorias(),
            'unidades'   => Configuracion::unidades(),
            'metodos'    => Configuracion::metodosPago(),
        ]);
    }

    public function guardarConfiguracion(): void
    {
        Auth::requerir('configuracion.gestionar');
        $valores = (array) ($_POST['valor'] ?? []);
        $v = new Validator(array_map(fn($x) => is_string($x) ? trim($x) : '', $valores));
        foreach (Configuracion::listar() as $p) {
            $clave = $p['clave'];
            if (!array_key_exists($clave, $valores)) {
                continue;
            }
            $v->requerido($clave, $p['descripcion'])->max($clave, 300);
            if (isset(Configuracion::REGLAS[$clave])) {
                [$min, $max] = Configuracion::REGLAS[$clave];
                $v->entero($clave, $min, $max);
            }
        }
        $v->email('CORREO')->telefono('TELEFONO');
        $this->validar($v, '/configuracion', ['valor' => $valores]);

        foreach (Configuracion::listar() as $p) {
            $nuevo = trim((string) ($valores[$p['clave']] ?? $p['valor']));
            if ($nuevo !== $p['valor']) {
                Configuracion::actualizar($p['clave'], $nuevo);
                Bitacora::registrar('EDITAR', 'CONFIGURACION', 'configuracion', null, "{$p['clave']}: \"{$p['valor']}\" → \"$nuevo\"");
            }
        }
        flash('ok', 'Configuración guardada.');
        redirect('/configuracion');
    }

    public function guardarCatalogo(): void
    {
        Auth::requerir('configuracion.gestionar');
        $tabla = entrada('tabla');
        $id = entrada_int('id');
        $d = ['nombre' => entrada('nombre'), 'tipo' => entrada('tipo'), 'abreviatura' => entrada('abreviatura')];
        $v = (new Validator($d))->requerido('nombre', 'El nombre')->longitud('nombre', 2, $tabla === 'unidad' ? 30 : 60);
        match ($tabla) {
            'categoria'   => $id ? null : $v->requerido('tipo', 'El tipo')->en('tipo', ['PIEZA', 'MATERIAL']),
            'unidad'      => $v->requerido('abreviatura', 'La abreviatura')->longitud('abreviatura', 1, 10),
            'metodo_pago' => null,
            default       => $this->noEncontrado(),
        };
        $this->validar($v, '/configuracion#catalogos', $d);
        if ($this->intentar(function () use ($tabla, $id, $d) {
            $nuevo = match ($tabla) {
                'categoria'   => Configuracion::guardarCategoria($id, $d['nombre'], $d['tipo']),
                'unidad'      => Configuracion::guardarUnidad($id, $d['nombre'], $d['abreviatura']),
                'metodo_pago' => Configuracion::guardarMetodo($id, $d['nombre']),
            };
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'CONFIGURACION', $tabla, $nuevo, Configuracion::CATALOGOS[$tabla] . ': ' . $d['nombre']);
        }, $d)) {
            flash('ok', Configuracion::CATALOGOS[$tabla] . ': cambios guardados.');
        }
        redirect('/configuracion#catalogos');
    }

    public function estadoCatalogo(): void
    {
        Auth::requerir('configuracion.gestionar');
        $tabla = entrada('tabla');
        $estado = Configuracion::cambiarEstadoCatalogo($tabla, $this->id()) ?? $this->noEncontrado();
        Bitacora::registrar($estado === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'CONFIGURACION', $tabla, entrada_int('id'), Configuracion::CATALOGOS[$tabla]);
        flash('ok', $estado === 'ACTIVO' ? 'Elemento activado.' : 'Elemento desactivado: ya no aparece en los formularios.');
        redirect('/configuracion#catalogos');
    }

    // ---------------------------------------------------------- Mi cuenta

    public function cuenta(): void
    {
        Auth::requerir();
        $this->vista('P1_Seguridad/cuenta', ['u' => Auth::usuario()]);
    }

    public function password(): void
    {
        $u = Auth::requerir();
        $d = ['actual' => $_POST['actual'] ?? '', 'password' => $_POST['password'] ?? '', 'password_confirmacion' => $_POST['password_confirmacion'] ?? ''];
        $v = (new Validator($d))->requerido('actual', 'La contraseña actual')
            ->password('password')->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.')
            ->si($d['actual'] !== '' && $d['actual'] === $d['password'], 'password', 'La nueva contraseña debe ser distinta.');
        $volver = Auth::puede('portal.alumno') ? '/portal/perfil' : '/cuenta';
        $this->validar($v, $volver);
        if (!Usuario::verificarPassword((int) $u['id'], (string) $d['actual'])) {
            con_errores(['actual' => 'La contraseña actual no es correcta.']);
            flash('error', 'La contraseña actual no es correcta.');
            redirect($volver);
        }
        Usuario::cambiarPassword((int) $u['id'], (string) $d['password']);
        Bitacora::registrar('CAMBIAR_CONTRASENA', 'SEGURIDAD', 'usuario', (int) $u['id'], 'Cambio de contraseña desde Mi cuenta');
        session_regenerate_id(true);
        flash('ok', 'Contraseña actualizada.');
        redirect($volver);
    }
}
