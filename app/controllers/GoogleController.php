<?php
/**
 * CU01 (alternativa) · Ingresar con Google (OAuth 2.0).
 * Se activa al configurar GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en las variables
 * de entorno, con la URI de redirección https://<dominio>/auth/google/callback.
 * Si el correo no tiene cuenta, se crea una cuenta de CLIENTE.
 */

declare(strict_types=1);

final class GoogleController extends Controlador
{
    public static function configurado(): bool
    {
        return (bool) (getenv('GOOGLE_CLIENT_ID') && getenv('GOOGLE_CLIENT_SECRET'));
    }

    private static function redireccion(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/auth/google/callback';
    }

    public function iniciar(): void
    {
        if (!self::configurado()) {
            flash('error', 'El ingreso con Google todavía no está habilitado. Use su correo y contraseña.');
            redirect('/tienda/ingresar');
        }
        $_SESSION['google_estado'] = bin2hex(random_bytes(16));   // protege contra CSRF en el regreso
        redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => getenv('GOOGLE_CLIENT_ID'),
            'redirect_uri'  => self::redireccion(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $_SESSION['google_estado'],
            'prompt'        => 'select_account',
        ]));
    }

    public function callback(): void
    {
        $estado = (string) ($_SESSION['google_estado'] ?? '');
        unset($_SESSION['google_estado']);
        if (!self::configurado() || $estado === '' || !hash_equals($estado, entrada('state')) || entrada('code') === '') {
            flash('error', 'No se pudo completar el ingreso con Google. Intente de nuevo.');
            redirect('/tienda/ingresar');
        }
        $token = self::http('https://oauth2.googleapis.com/token', [
            'code' => entrada('code'), 'client_id' => getenv('GOOGLE_CLIENT_ID'), 'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => self::redireccion(), 'grant_type' => 'authorization_code',
        ]);
        $perfil = isset($token['access_token']) ? self::http('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']) : null;
        if (!$perfil || empty($perfil['email']) || empty($perfil['email_verified'])) {
            flash('error', 'Google no confirmó su correo. Intente con otra cuenta.');
            redirect('/tienda/ingresar');
        }

        $correo = mb_strtolower($perfil['email']);
        $u = Database::fila('select id, estado from usuario where lower(correo) = :c', ['c' => $correo]);
        if (!$u) {
            $nombre = mb_substr(trim($perfil['given_name'] ?? 'Cliente'), 0, 60) ?: 'Cliente';
            $apellido = mb_substr(trim($perfil['family_name'] ?? 'Google'), 0, 60) ?: 'Google';
            $id = Usuario::crear(['rol_id' => Rol::idPorNombre('CLIENTE'), 'nombre' => mb_strlen($nombre) >= 2 ? $nombre : 'Cliente',
                'apellido' => mb_strlen($apellido) >= 2 ? $apellido : 'Google', 'correo' => $correo, 'telefono' => '',
                'password' => bin2hex(random_bytes(24))]);   // contraseña aleatoria: entra con Google o recupera la clave
            Database::query('insert into cliente (nombre, correo, usuario_id) values (:n, :c, :u)', ['n' => "$nombre $apellido", 'c' => $correo, 'u' => $id]);
            Bitacora::registrar('REGISTRO', 'TIENDA', 'usuario', $id, "Cliente se registró con Google: $correo", null);
            $u = ['id' => $id, 'estado' => 'ACTIVO'];
        }
        if ($u['estado'] !== 'ACTIVO') {
            flash('error', 'Su cuenta está desactivada. Contacte al taller.');
            redirect('/tienda/ingresar');
        }
        Auth::iniciarPorId((int) $u['id'], 'Inicio de sesión con Google');
        flash('ok', '¡Hola! Ingresaste con tu cuenta de Google.');
        redirect(TiendaController::destino(Auth::inicio()));
    }

    private static function http(string $url, ?array $post, ?string $bearer = null): ?array
    {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        if ($post !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if ($bearer) {
            curl_setopt($c, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $bearer]);
        }
        $r = curl_exec($c);
        $ok = curl_getinfo($c, CURLINFO_HTTP_CODE) === 200;
        curl_close($c);
        return $ok && is_string($r) ? json_decode($r, true) : null;
    }
}
