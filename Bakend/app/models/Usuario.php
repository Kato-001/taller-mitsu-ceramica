<?php
/**
 * USUARIO · Cuentas de acceso al sistema (CU03).
 */

declare(strict_types=1);

final class Usuario
{
    private const SELECT = "select u.*, u.nombre || ' ' || u.apellido as nombre_completo, r.nombre as rol,
                                   m.id as maestra_id, a.id as alumno_id
                            from usuario u
                            join rol r on r.id = u.rol_id
                            left join maestra m on m.usuario_id = u.id
                            left join alumno a on a.usuario_id = u.id";

    public static function listar(array $f, int $limite, int $offset): array
    {
        [$where, $p] = self::filtros($f);
        return Database::filas(self::SELECT . " $where order by u.estado, u.nombre, u.apellido limit $limite offset $offset", $p);
    }

    public static function contar(array $f): int
    {
        [$where, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from usuario u $where", $p);
    }

    /** Cantidad por estado para las pestañas: '' (todos), ACTIVO, INACTIVO. */
    public static function conteoPorEstado(array $f): array
    {
        $c = ['' => 0, 'ACTIVO' => 0, 'INACTIVO' => 0];
        [$where, $p] = self::filtros(['estado' => ''] + $f);
        foreach (Database::filas("select u.estado, count(*) n from usuario u $where group by u.estado", $p) as $r) {
            $c[$r['estado']] = (int) $r['n'];
            $c[''] += (int) $r['n'];
        }
        return $c;
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where u.id = :id', ['id' => $id]);
    }

    public static function existeCorreo(string $correo, ?int $exceptoId = null): bool
    {
        return (bool) Database::valor(
            'select 1 from usuario where lower(correo) = lower(:c) and id <> coalesce(cast(:id as integer), 0)',
            ['c' => $correo, 'id' => $exceptoId]
        );
    }

    public static function crear(array $d): int
    {
        return (int) Database::valor(
            'insert into usuario (rol_id, nombre, apellido, correo, telefono, clave_hash)
             values (:r, :n, :a, lower(:c), :t, :h) returning id',
            ['r' => (int) $d['rol_id'], 'n' => $d['nombre'], 'a' => $d['apellido'], 'c' => $d['correo'],
             't' => $d['telefono'] ?: null, 'h' => password_hash($d['password'], PASSWORD_DEFAULT)]
        );
    }

    public static function actualizar(int $id, array $d): void
    {
        Database::query(
            'update usuario set rol_id = :r, nombre = :n, apellido = :a, correo = lower(:c), telefono = :t where id = :id',
            ['r' => (int) $d['rol_id'], 'n' => $d['nombre'], 'a' => $d['apellido'], 'c' => $d['correo'],
             't' => $d['telefono'] ?: null, 'id' => $id]
        );
        if (($d['password'] ?? '') !== '') {
            self::cambiarPassword($id, $d['password']);
        }
    }

    public static function cambiarPassword(int $id, string $password): void
    {
        Database::query('update usuario set clave_hash = :h where id = :id',
            ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function verificarPassword(int $id, string $password): bool
    {
        $hash = Database::valor('select clave_hash from usuario where id = :id', ['id' => $id]);
        return $hash && password_verify($password, $hash);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        Database::query('update usuario set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
        if ($estado === 'INACTIVO') {
            // Cierra sus sesiones abiertas
            Database::query('delete from sesion where datos like :p', ['p' => '%usuario_id|i:' . $id . ';%']);
        }
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = "(normalizar(u.nombre || ' ' || u.apellido) like '%' || normalizar(:q) || '%' or u.correo ilike '%' || :q || '%')";
            $p['q'] = $f['q'];
        }
        if (!empty($f['rol'])) {
            $w[] = 'u.rol_id = :rol';
            $p['rol'] = (int) $f['rol'];
        }
        if (!empty($f['estado'])) {
            $w[] = 'u.estado = :estado';
            $p['estado'] = $f['estado'];
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
