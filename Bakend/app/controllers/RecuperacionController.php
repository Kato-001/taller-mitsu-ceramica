<?php
/**
 * CU02 · Recuperar la contraseña con un código enviado al correo.
 *
 *   1. /recuperar         el usuario escribe su correo → se envía un código de 6 dígitos
 *   2. /recuperar/codigo  escribe el código y la nueva contraseña → se restablece
 *
 * Seguridad: el código se guarda con password_hash(), vence en 15 minutos, admite
 * 5 intentos, se limita la cantidad de solicitudes y la respuesta es la misma exista
 * o no el correo (no se revela qué correos están registrados).
 * Modo demostración: si no hay SMTP configurado, el código se muestra en la pantalla.
 */

declare(strict_types=1);

final class RecuperacionController extends Controlador
{
    private const AVISO = 'Si el correo está registrado, te enviamos un código de 6 dígitos. Revisa tu bandeja de entrada y la carpeta de spam.';

    public function formulario(): void
    {
        if (Auth::usuario()) {
            redirect(Auth::inicio());
        }
        $this->vista('P1_Seguridad/recuperar', [], 'auth');
    }

    public function enviar(): void
    {
        $correo = mb_strtolower(entrada('correo'));
        $v = (new Validator(['correo' => $correo]))->requerido('correo', 'El correo')->email('correo')->max('correo', 120);
        $this->validar($v, '/recuperar', ['correo' => $correo]);

        if (Recuperacion::solicitudesPorIp(ip_cliente()) >= Recuperacion::MAX_POR_IP) {
            flash('error', 'Demasiadas solicitudes. Espere unos minutos e intente de nuevo.');
            redirect('/recuperar');
        }
        $u = Recuperacion::usuarioPorCorreo($correo);

        // Sin servidor de correo (modo demostración) el código se muestra en la pantalla para copiarlo
        if (!Mailer::configurado()) {
            if (!$u) {
                flash('error', 'No hay una cuenta activa con ese correo.');
                redirect('/recuperar');
            }
            if (Recuperacion::solicitudesRecientes((int) $u['id']) >= Recuperacion::MAX_SOLICITUDES) {
                $_SESSION['recuperar_correo'] = $correo;
                flash('error', 'Ya se generaron varios códigos para este correo. Use el último o espere 15 minutos.');
                redirect('/recuperar/codigo');
            }
            $_SESSION['recuperar_correo'] = $correo;
            $_SESSION['recuperar_demo'] = Recuperacion::crear((int) $u['id']);
            Bitacora::registrar('RECUPERAR_SOLICITUD', 'SEGURIDAD', 'usuario', (int) $u['id'], 'Código de recuperación mostrado en pantalla (sin correo)', (int) $u['id']);
            flash('ok', 'Generamos tu código de verificación. Cópialo y pégalo abajo.');
            redirect('/recuperar/codigo');
        }
        if ($u) {
            if (Recuperacion::solicitudesRecientes((int) $u['id']) >= Recuperacion::MAX_SOLICITUDES) {
                $_SESSION['recuperar_correo'] = $correo;
                flash('error', 'Ya se enviaron varios códigos a este correo. Espere 15 minutos para pedir otro.');
                redirect('/recuperar/codigo');
            }
            $codigo = Recuperacion::crear((int) $u['id']);
            try {
                [$html, $texto] = Recuperacion::correo($u['nombre'], $codigo);
                Mailer::enviar($u['correo'], 'Tu código de verificación: ' . $codigo, $html, $texto);
                Bitacora::registrar('RECUPERAR_SOLICITUD', 'SEGURIDAD', 'usuario', (int) $u['id'], 'Código de recuperación enviado al correo', (int) $u['id']);
            } catch (Throwable $e) {
                error_log('[Mitsu] No se pudo enviar el correo de recuperación: ' . $e->getMessage());
                if ($r = Recuperacion::vigente((int) $u['id'])) {
                    Recuperacion::anular((int) $r['id']);
                }
                flash('error', 'No pudimos enviar el correo en este momento. Intente más tarde o contacte a la administración.');
                redirect('/recuperar');
            }
        }

        // La misma respuesta exista o no la cuenta
        $_SESSION['recuperar_correo'] = $correo;
        flash('ok', self::AVISO);
        redirect('/recuperar/codigo');
    }

    public function codigo(): void
    {
        if (empty($_SESSION['recuperar_correo'])) {
            redirect('/recuperar');
        }
        $this->vista('P1_Seguridad/recuperar_codigo', [
            'correo' => $_SESSION['recuperar_correo'],
            'demo'   => $_SESSION['recuperar_demo'] ?? null,
        ], 'auth');
    }

    public function restablecer(): void
    {
        $correo = (string) ($_SESSION['recuperar_correo'] ?? '');
        if ($correo === '') {
            redirect('/recuperar');
        }
        $d = ['codigo' => preg_replace('/\D/', '', entrada('codigo')), 'password' => $_POST['password'] ?? '', 'password_confirmacion' => $_POST['password_confirmacion'] ?? ''];
        $v = new Validator($d);
        $v->requerido('codigo', 'El código')->regex('codigo', '/^\d{6}$/', 'El código tiene 6 dígitos.')
          ->password('password')->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.');
        $this->validar($v, '/recuperar/codigo');

        $u = Recuperacion::usuarioPorCorreo($correo);
        $r = $u ? Recuperacion::vigente((int) $u['id']) : null;
        if (!$r) {
            flash('error', 'El código no es válido o ya venció. Solicite uno nuevo.');
            redirect('/recuperar/codigo');
        }
        if ((int) $r['intentos'] >= Recuperacion::MAX_INTENTOS) {
            Recuperacion::anular((int) $r['id']);
            flash('error', 'Se superó el número de intentos. Solicite un código nuevo.');
            redirect('/recuperar');
        }
        if (!password_verify($d['codigo'], $r['codigo_hash'])) {
            Recuperacion::sumarIntento((int) $r['id']);
            $quedan = Recuperacion::MAX_INTENTOS - (int) $r['intentos'] - 1;
            Bitacora::registrar('RECUPERAR_FALLIDO', 'SEGURIDAD', 'usuario', (int) $u['id'], 'Código de recuperación incorrecto', (int) $u['id']);
            flash('error', $quedan > 0 ? "Código incorrecto. Le quedan $quedan intento(s)." : 'Código incorrecto. Solicite un código nuevo.');
            redirect($quedan > 0 ? '/recuperar/codigo' : '/recuperar');
        }

        Recuperacion::restablecer($r, $d['password']);
        Bitacora::registrar('RECUPERAR_CONTRASENA', 'SEGURIDAD', 'usuario', (int) $u['id'], 'Contraseña restablecida con código enviado al correo', (int) $u['id']);
        unset($_SESSION['recuperar_correo'], $_SESSION['recuperar_demo']);
        flash('ok', 'Tu contraseña fue restablecida. Ya puedes ingresar con la nueva contraseña.');
        redirect('/login');
    }
}
