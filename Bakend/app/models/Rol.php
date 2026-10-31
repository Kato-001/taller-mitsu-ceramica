<?php
/**
 * ROL y PERMISO · Roles de usuario y los permisos asignados a cada uno (CU04).
 */

declare(strict_types=1);

final class Rol
{
    public const DEL_SISTEMA = ['ADMINISTRADOR', 'ADMINISTRATIVO', 'MAESTRA', 'ALUMNO', 'CLIENTE'];

    /** Permisos que el ADMINISTRADOR nunca pierde (evita quedarse sin acceso). */
    public const PERMISOS_ADMIN_FIJOS = ['usuarios.gestionar', 'roles.gestionar'];

    public static function todos(bool $soloActivos = false): array
    {
        return Database::filas(
            'select r.*, (select count(*) from usuario u where u.rol_id = r.id) as usuarios
             from rol r ' . ($soloActivos ? "where r.estado = 'ACTIVO' " : '') . 'order by r.id'
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila('select * from rol where id = :id', ['id' => $id]);
    }

    public static function idPorNombre(string $nombre): int
    {
        return (int) Database::valor('select id from rol where nombre = :n', ['n' => $nombre]);
    }

    public static function crear(string $nombre, ?string $descripcion): int
    {
        return (int) Database::valor('insert into rol (nombre, descripcion) values (:n, :d) returning id',
            ['n' => $nombre, 'd' => $descripcion]);
    }

    public static function actualizarDescripcion(int $id, ?string $descripcion): void
    {
        Database::query('update rol set descripcion = :d where id = :id', ['d' => $descripcion, 'id' => $id]);
    }

    /** Los roles no se borran: se desactivan (solo si no tienen usuarios activos). */
    public static function cambiarEstado(int $id, string $estado): void
    {
        Database::query('update rol set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }

    public static function permisos(): array
    {
        return Database::filas('select * from permiso order by modulo, id');
    }

    /** @return array<int, int[]> rol_id => [permiso_id, …] */
    public static function matrizPermisos(): array
    {
        $m = [];
        foreach (Database::filas('select rol_id, permiso_id from rolpermiso') as $f) {
            $m[(int) $f['rol_id']][] = (int) $f['permiso_id'];
        }
        return $m;
    }

    /** Reemplaza los permisos del rol. */
    public static function asignarPermisos(int $rolId, array $idsPermiso): void
    {
        Database::transaccion(function () use ($rolId, $idsPermiso) {
            Database::query('delete from rolpermiso where rol_id = :r', ['r' => $rolId]);
            foreach (array_unique(array_map('intval', $idsPermiso)) as $idp) {
                Database::query('insert into rolpermiso (rol_id, permiso_id) select :r, id from permiso where id = :p',
                    ['r' => $rolId, 'p' => $idp]);
            }
        });
    }
}
