<?php
/**
 * CU01 · Iniciar y cerrar sesión · Registro público de alumnos (CU12, alta propia).
 */

declare(strict_types=1);

final class AuthController extends Controlador
{
    /** Ingreso desde la tienda (mismo formulario, con la opción de Google). */
    public function ingresarTienda(): void
    {
        if (Auth::usuario()) {
            redirect(TiendaController::destino(Auth::inicio()));
        }
        $this->vista('Tienda/ingresar', ['google' => GoogleController::configurado()], 'tienda');
    }

    public function formulario(): void
    {
        if (Auth::usuario()) {
            redirect(Auth::inicio());
        }
        $this->vista('P1_Seguridad/login', [], 'auth');
    }

    public function ingresar(): void
    {
        $correo = mb_strtolower(entrada('correo'));
        $password = $_POST['password'] ?? '';

        $v = new Validator(['correo' => $correo, 'password' => $password]);
        $v->requerido('correo', 'El correo')->email('correo')->max('correo', 120)
          ->requerido('password', 'La contraseña')->max('password', 100);
        $this->validar($v, entrada('origen') === 'tienda' ? '/tienda/ingresar' : '/login', ['correo' => $correo]);

        try {
            Auth::intentar($correo, (string) $password);
        } catch (AppException $e) {
            con_errores([], ['correo' => $correo]);
            flash('error', $e->getMessage());
            redirect(entrada('origen') === 'tienda' ? '/tienda/ingresar' : '/login');
        }
        redirect(TiendaController::destino(Auth::inicio()));
    }

    public function salir(): void
    {
        if (Auth::usuario()) {
            Bitacora::registrar('CERRAR_SESION', 'SEGURIDAD', 'usuario', Auth::id(), 'Cierre de sesión');
        }
        Auth::cerrarSesion();
        flash('ok', 'Sesión cerrada correctamente.');
        redirect('/login');
    }

    // ------------------------------------------- Registro de alumnos (portal)

    public function registro(): void
    {
        if (Auth::usuario()) {
            redirect(Auth::inicio());
        }
        $this->vista('P2_Clases/registro', ['talleres' => Taller::todos(true)], 'auth');
    }

    public function registrar(): void
    {
        $d = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
        $d['correo'] = mb_strtolower($d['correo'] ?? '');
        $d['nivel'] = 'INICIAL';
        $v = AlumnoController::validador($d, null)
            ->password('password')->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.')
            ->si(empty($d['acepta']), 'acepta', 'Debe aceptar el uso de sus datos para las reservas.');
        $this->validar($v, '/registro', $d);

        $ok = $this->intentar(function () use ($d) {
            $id = Alumno::crear($d);
            Bitacora::registrar('REGISTRO', 'CLASES', 'alumno', $id, 'Alumno se registró desde el portal: ' . $d['correo'], null);
        }, $d);
        if (!$ok) {
            redirect('/registro');
        }
        Auth::intentar($d['correo'], $d['password']);
        flash('ok', '¡Bienvenido a Mitsü! Tu cuenta fue creada. Ya puedes reservar tu primera clase.');
        redirect('/portal/reservar');
    }
}
