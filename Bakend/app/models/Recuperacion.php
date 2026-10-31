<?php
/**
 * RECUPERACION · Códigos de verificación para recuperar la contraseña (CU02).
 */

declare(strict_types=1);

final class Recuperacion
{
    public const MINUTOS_VIGENCIA = 15;
    public const MAX_INTENTOS = 5;
    public const MAX_SOLICITUDES = 3;   // por cuenta cada 15 minutos
    public const MAX_POR_IP = 6;        // por IP cada 15 minutos

    /** Cuenta activa con ese correo (sin el hash de la contraseña). */
    public static function usuarioPorCorreo(string $correo): ?array
    {
        return Database::fila(
            "select id, nombre, correo from usuario where lower(correo) = lower(:c) and estado = 'ACTIVO'",
            ['c' => $correo]
        );
    }

    public static function solicitudesRecientes(int $usuarioId): int
    {
        return (int) Database::valor(
            'select count(*) from recuperacion where usuario_id = :u and creado_en > now() - make_interval(mins => :m)',
            ['u' => $usuarioId, 'm' => self::MINUTOS_VIGENCIA]
        );
    }

    public static function solicitudesPorIp(string $ip): int
    {
        return (int) Database::valor(
            'select count(*) from recuperacion where ip = :ip and creado_en > now() - make_interval(mins => :m)',
            ['ip' => $ip, 'm' => self::MINUTOS_VIGENCIA]
        );
    }

    /** Genera un código de 6 dígitos, anula los anteriores y guarda solo su hash. */
    public static function crear(int $usuarioId): string
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Database::transaccion(function () use ($usuarioId, $codigo) {
            Database::query('update recuperacion set usado = true where usuario_id = :u and not usado', ['u' => $usuarioId]);
            Database::query(
                'insert into recuperacion (usuario_id, codigo_hash, expira_en, ip)
                 values (:u, :h, now() + make_interval(mins => :m), :ip)',
                ['u' => $usuarioId, 'h' => password_hash($codigo, PASSWORD_DEFAULT), 'm' => self::MINUTOS_VIGENCIA, 'ip' => ip_cliente()]
            );
        });
        return $codigo;
    }

    /** Código vigente (no usado y no vencido) más reciente de la cuenta. */
    public static function vigente(int $usuarioId): ?array
    {
        return Database::fila(
            'select * from recuperacion where usuario_id = :u and not usado and expira_en > now() order by creado_en desc limit 1',
            ['u' => $usuarioId]
        );
    }

    public static function sumarIntento(int $id): void
    {
        Database::query('update recuperacion set intentos = least(intentos + 1, 5) where id = :id', ['id' => $id]);
    }

    public static function anular(int $id): void
    {
        Database::query('update recuperacion set usado = true where id = :id', ['id' => $id]);
    }

    /** Cambia la contraseña, marca el código como usado y cierra las sesiones abiertas. */
    public static function restablecer(array $r, string $password): void
    {
        Database::transaccion(function () use ($r, $password) {
            Usuario::cambiarPassword((int) $r['usuario_id'], $password);
            Database::query('update recuperacion set usado = true where usuario_id = :u', ['u' => $r['usuario_id']]);
            Database::query('delete from sesion where datos like :p', ['p' => '%usuario_id|i:' . (int) $r['usuario_id'] . ';%']);
        });
    }

    /** Correo con el código (HTML y texto plano). */
    public static function correo(string $nombre, string $codigo): array
    {
        $min = self::MINUTOS_VIGENCIA;
        $marca = e(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü'));
        $nombre = e($nombre);
        $html = <<<HTML
<!doctype html><html><body style="margin:0;background:#f7f2ec;font-family:Segoe UI,Arial,sans-serif;color:#2b211c">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f7f2ec;padding:32px 12px"><tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #eadfd4">
<tr><td style="background:linear-gradient(135deg,#c2693f,#a0522d,#6b4f3a);background-color:#a0522d;padding:24px 28px;color:#ffffff">
<div style="font-size:20px;font-weight:800">Mitsü</div><div style="font-size:13px;opacity:.85">{$marca}</div></td></tr>
<tr><td style="padding:28px">
<p style="margin:0 0 12px;font-size:16px">Hola, <strong>{$nombre}</strong>:</p>
<p style="margin:0 0 20px;font-size:15px;color:#6b5a4e">Recibimos una solicitud para restablecer tu contraseña. Usa este código de verificación:</p>
<div style="text-align:center;margin:0 0 20px"><span style="display:inline-block;font-family:Consolas,monospace;font-size:34px;font-weight:700;letter-spacing:10px;padding:14px 22px;border-radius:12px;background:#fbf1ea;border:1px solid #e7c3ab;color:#8a4524">{$codigo}</span></div>
<p style="margin:0 0 8px;font-size:14px;color:#6b5a4e">El código vence en <strong>{$min} minutos</strong> y solo se puede usar una vez.</p>
<p style="margin:0;font-size:13px;color:#a39385">Si no solicitaste este cambio, ignora este mensaje: tu contraseña seguirá siendo la misma. Nunca compartas este código.</p>
</td></tr></table></td></tr></table></body></html>
HTML;
        $texto = "Hola, {$nombre}:\n\nTu código para restablecer la contraseña del Taller Mitsü es: {$codigo}\n\n"
            . "Vence en {$min} minutos y solo se puede usar una vez. Si no lo solicitaste, ignora este mensaje.";
        return [$html, $texto];
    }
}
