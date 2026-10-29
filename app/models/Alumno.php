<?php
/**
 * ALUMNO · Personas que toman clases (CU12). Cada alumno tiene su cuenta para el portal.
 */

declare(strict_types=1);

final class Alumno
{
    private const SELECT = "select a.*, u.nombre, u.apellido, u.nombre || ' ' || u.apellido as nombre_completo,
                                   u.correo, u.telefono, u.creado_en, u.ultimo_acceso,
                                   (select count(*) from reserva r where r.alumno_id = a.id and r.estado = 'ASISTIO') as asistencias,
                                   (select count(*) from reserva r where r.alumno_id = a.id and r.estado = 'FALTO') as faltas
                            from alumno a join usuario u on u.id = a.usuario_id";

    public static function listar(array $f, int $limite, int $offset): array
    {
        [$where, $p] = self::filtros($f);
        return Database::filas(self::SELECT . " $where order by a.estado, u.nombre, u.apellido limit $limite offset $offset", $p);
    }

    public static function contar(array $f): int
    {
        [$where, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from alumno a join usuario u on u.id = a.usuario_id $where", $p);
    }

    public static function activos(): array
    {
        return Database::filas(self::SELECT . " where a.estado = 'ACTIVO' and u.estado = 'ACTIVO' order by u.nombre, u.apellido");
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where a.id = :id', ['id' => $id]);
    }

    public static function existeCi(string $ci, ?int $exceptoId = null): bool
    {
        return (bool) Database::valor('select 1 from alumno where ci = :ci and id <> coalesce(cast(:id as integer), 0)',
            ['ci' => $ci, 'id' => $exceptoId]);
    }

    /** Crea la cuenta (rol ALUMNO) y la ficha del alumno en una sola transacción. */
    public static function crear(array $d): int
    {
        return Auth::transaccion(function () use ($d) {
            $usuarioId = Usuario::crear($d + ['rol_id' => Rol::idPorNombre('ALUMNO')]);
            return (int) Database::valor(
                'insert into alumno (usuario_id, ci, fecha_nacimiento, nivel) values (:u, :ci, cast(:f as date), :n) returning id',
                ['u' => $usuarioId, 'ci' => $d['ci'] ?: null, 'f' => $d['fecha_nacimiento'] ?: null, 'n' => $d['nivel'] ?: 'INICIAL']
            );
        });
    }

    public static function actualizar(int $id, array $d, bool $cambiaNivel = true): void
    {
        Auth::transaccion(function () use ($id, $d, $cambiaNivel) {
            $a = self::buscar($id);
            Database::query('update usuario set nombre = :n, apellido = :a, correo = lower(:c), telefono = :t where id = :u',
                ['n' => $d['nombre'], 'a' => $d['apellido'], 'c' => $d['correo'], 't' => $d['telefono'] ?: null, 'u' => $a['usuario_id']]);
            Database::query('update alumno set ci = :ci, fecha_nacimiento = cast(:f as date), nivel = :n where id = :id',
                ['ci' => $d['ci'] ?: null, 'f' => $d['fecha_nacimiento'] ?: null, 'n' => $cambiaNivel ? $d['nivel'] : $a['nivel'], 'id' => $id]);
        });
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        Auth::transaccion(function () use ($id, $estado) {
            $usuarioId = (int) Database::valor('update alumno set estado = :e where id = :id returning usuario_id', ['e' => $estado, 'id' => $id]);
            Usuario::cambiarEstado($usuarioId, $estado);
        });
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = "(normalizar(u.nombre || ' ' || u.apellido) like '%' || normalizar(:q) || '%' or a.ci like :q || '%' or u.correo ilike '%' || :q || '%')";
            $p['q'] = $f['q'];
        }
        if (!empty($f['nivel'])) {
            $w[] = 'a.nivel = :nivel';
            $p['nivel'] = $f['nivel'];
        }
        if (!empty($f['estado'])) {
            $w[] = 'a.estado = :estado';
            $p['estado'] = $f['estado'];
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
