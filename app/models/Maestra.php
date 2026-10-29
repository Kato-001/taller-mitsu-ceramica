<?php
/**
 * MAESTRA · Docentes del taller (CU07). Cada maestra tiene su cuenta de usuario.
 */

declare(strict_types=1);

final class Maestra
{
    private const SELECT = "select m.*, u.nombre, u.apellido, u.nombre || ' ' || u.apellido as nombre_completo,
                                   u.correo, u.telefono, u.estado as estado_usuario,
                                   (select count(*) from horario h where h.maestra_id = m.id and h.estado = 'ACTIVO') as horarios
                            from maestra m join usuario u on u.id = m.usuario_id";

    public static function todas(): array
    {
        return Database::filas(self::SELECT . ' order by m.estado, u.nombre');
    }

    public static function activas(): array
    {
        return Database::filas(self::SELECT . " where m.estado = 'ACTIVO' and u.estado = 'ACTIVO' order by u.nombre");
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where m.id = :id', ['id' => $id]);
    }

    /** Crea la cuenta (rol MAESTRA) y la ficha de la maestra en una sola transacción. */
    public static function crear(array $d): int
    {
        return Auth::transaccion(function () use ($d) {
            $usuarioId = Usuario::crear($d + ['rol_id' => Rol::idPorNombre('MAESTRA')]);
            return (int) Database::valor(
                'insert into maestra (usuario_id, especialidad, biografia) values (:u, :e, :b) returning id',
                ['u' => $usuarioId, 'e' => $d['especialidad'], 'b' => $d['biografia'] ?: null]
            );
        });
    }

    public static function actualizar(int $id, array $d): void
    {
        Auth::transaccion(function () use ($id, $d) {
            $m = self::buscar($id);
            Database::query('update usuario set nombre = :n, apellido = :a, correo = lower(:c), telefono = :t where id = :u',
                ['n' => $d['nombre'], 'a' => $d['apellido'], 'c' => $d['correo'], 't' => $d['telefono'] ?: null, 'u' => $m['usuario_id']]);
            Database::query('update maestra set especialidad = :e, biografia = :b where id = :id',
                ['e' => $d['especialidad'], 'b' => $d['biografia'] ?: null, 'id' => $id]);
            if (($d['password'] ?? '') !== '') {
                Usuario::cambiarPassword((int) $m['usuario_id'], $d['password']);
            }
        });
    }

    /** Desactivar a la maestra también desactiva su cuenta. Sus horarios activos lo impiden. */
    public static function cambiarEstado(int $id, string $estado): void
    {
        Auth::transaccion(function () use ($id, $estado) {
            if ($estado === 'INACTIVO' && Database::valor("select 1 from horario where maestra_id = :id and estado = 'ACTIVO'", ['id' => $id])) {
                throw new AppException('La maestra tiene horarios activos. Reasígnelos o desactívelos primero.');
            }
            $usuarioId = (int) Database::valor('update maestra set estado = :e where id = :id returning usuario_id', ['e' => $estado, 'id' => $id]);
            Usuario::cambiarEstado($usuarioId, $estado);
        });
    }
}
