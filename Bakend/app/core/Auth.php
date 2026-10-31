<?php
/**
 * CU01 · Iniciar sesión y control de acceso.
 * Valida credenciales con password_verify(), mantiene la sesión y
 * comprueba los permisos del rol en cada petición.
 */

declare(strict_types=1);

final class Auth
{
    private const MAX_INTENTOS = 5;      // intentos fallidos permitidos…
    private const VENTANA_MINUTOS = 10;  // …por IP en esta ventana

    private static ?array $usuario = null;
    private static bool $cargado = false;

    /**
     * Intenta iniciar sesión con el correo.
     * @throws AppException con un mensaje genérico si las credenciales no son válidas
     */
    public static function intentar(string $correo, string $password): array
    {
        $ip = ip_cliente();
        $fallidos = (int) Database::valor(
            "select count(*) from bitacora where accion = 'LOGIN_FALLIDO' and ip = :ip
             and fecha > now() - make_interval(mins => :min)",
            ['ip' => $ip, 'min' => self::VENTANA_MINUTOS]
        );
        if ($fallidos >= self::MAX_INTENTOS) {
            throw new AppException('Demasiados intentos fallidos. Espere unos minutos e intente de nuevo.');
        }

        $u = Database::fila(
            'select id, clave_hash, estado from usuario where lower(correo) = lower(:c)',
            ['c' => $correo]
        );

        if (!$u || !password_verify($password, $u['clave_hash'])) {
            Bitacora::registrar('LOGIN_FALLIDO', 'SEGURIDAD', 'usuario', $u['id'] ?? null, 'Intento fallido para "' . mb_substr($correo, 0, 60) . '"', null);
            throw new AppException('Correo o contraseña incorrectos.');
        }
        if ($u['estado'] !== 'ACTIVO') {
            throw new AppException('Su cuenta está desactivada. Contacte a la administración del taller.');
        }

        // Si el algoritmo de hash cambió, se actualiza el hash guardado
        if (password_needs_rehash($u['clave_hash'], PASSWORD_DEFAULT)) {
            Database::query('update usuario set clave_hash = :h where id = :id',
                ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $u['id']]);
        }

        session_regenerate_id(true);   // evita la fijación de sesión
        $_SESSION['usuario_id'] = (int) $u['id'];
        Database::query('update usuario set ultimo_acceso = now() where id = :id', ['id' => $u['id']]);
        self::$cargado = false;
        Bitacora::registrar('INICIAR_SESION', 'SEGURIDAD', 'usuario', (int) $u['id'], 'Inicio de sesión');
        return self::usuario();
    }

    /** Inicia la sesión de una cuenta ya verificada por otro medio (ingreso con Google). */
    public static function iniciarPorId(int $id, string $detalle): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $id;
        Database::query('update usuario set ultimo_acceso = now() where id = :id', ['id' => $id]);
        self::$cargado = false;
        Bitacora::registrar('INICIAR_SESION', 'SEGURIDAD', 'usuario', $id, $detalle);
    }

    /** Usuario de la sesión con su rol, permisos y vínculos (maestra / alumno). */
    public static function usuario(): ?array
    {
        if (self::$cargado) {
            return self::$usuario;
        }
        self::$cargado = true;
        $id = $_SESSION['usuario_id'] ?? null;
        if (!$id) {
            return self::$usuario = null;
        }

        $u = Database::fila(
            "select u.id, u.nombre, u.apellido, u.nombre || ' ' || u.apellido as nombre_completo, u.correo,
                    u.telefono, u.estado, u.ultimo_acceso, u.rol_id, r.nombre as rol,
                    m.id as maestra_id, a.id as alumno_id,
                    coalesce((select string_agg(p.codigo, ',') from rolpermiso rp
                              join permiso p on p.id = rp.permiso_id where rp.rol_id = u.rol_id), '') as permisos
             from usuario u
             join rol r on r.id = u.rol_id
             left join maestra m on m.usuario_id = u.id
             left join alumno a on a.usuario_id = u.id
             where u.id = :id",
            ['id' => $id]
        );

        // Una cuenta desactivada pierde la sesión en su siguiente petición
        if (!$u || $u['estado'] !== 'ACTIVO') {
            self::cerrarSesion();
            return self::$usuario = null;
        }
        $u['permisos'] = array_filter(explode(',', $u['permisos']));
        return self::$usuario = $u;
    }

    public static function id(): ?int
    {
        return self::usuario()['id'] ?? null;
    }

    public static function puede(string ...$permisos): bool
    {
        $u = self::usuario();
        return $u && array_intersect($permisos, $u['permisos']);
    }

    /** Detiene la petición si no hay sesión o falta el permiso (se verifica en el servidor). */
    public static function requerir(string ...$permisos): array
    {
        $u = self::usuario();
        if (!$u) {
            if (es_ajax()) {
                json_response(['ok' => false, 'error' => 'Sesión expirada'], 401);
            }
            flash('error', 'Inicie sesión para continuar.');
            redirect('/login');
        }
        if ($permisos && !self::puede(...$permisos)) {
            http_response_code(403);
            if (es_ajax()) {
                json_response(['ok' => false, 'error' => 'Acceso denegado'], 403);
            }
            View::render('errors/403');
            exit;
        }
        return $u;
    }

    /** Página de inicio según el rol. */
    public static function inicio(): string
    {
        return match (true) {
            self::puede('panel.ver')     => '/panel',
            self::puede('portal.alumno') => '/portal',
            default                      => '/tienda',
        };
    }

    /**
     * Ejecuta $fn en una transacción indicando a la BD quién opera
     * (lo usan los triggers de historial y auditoría).
     */
    public static function transaccion(callable $fn): mixed
    {
        return Database::transaccion(function () use ($fn) {
            Database::query("select set_config('mitsu.usuario_id', :u, true)", ['u' => (string) (self::id() ?? '')]);
            return $fn();
        });
    }

    public static function cerrarSesion(): void
    {
        $carrito = $_SESSION['carrito'] ?? null;   // el carrito de la tienda se conserva
        $_SESSION = $carrito ? ['carrito' => $carrito] : [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$usuario = null;
    }
}
