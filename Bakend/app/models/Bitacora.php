<?php
/**
 * BITACORA · Registro de auditoría de las operaciones del sistema (CU05).
 */

declare(strict_types=1);

final class Bitacora
{
    /**
     * Registra una acción. Si no se indica $usuarioId se usa el de la sesión.
     * Un fallo al auditar nunca debe interrumpir la operación principal.
     */
    public static function registrar(string $accion, string $modulo, ?string $tabla, ?int $registro, string $detalle, int|null|false $usuarioId = false): void
    {
        try {
            Database::query(
                'insert into bitacora (accion, modulo, tabla, registro, detalle, ip, usuario_id)
                 values (:a, :m, :t, :r, :d, :ip, :u)',
                [
                    'a' => $accion, 'm' => $modulo, 't' => $tabla, 'r' => $registro,
                    'd' => mb_substr($detalle, 0, 500), 'ip' => ip_cliente(),
                    'u' => $usuarioId === false ? Auth::id() : $usuarioId,
                ]
            );
        } catch (Throwable $e) {
            error_log('[Mitsu] No se pudo registrar la bitácora: ' . $e->getMessage());
        }
    }

    public static function listar(array $f, int $limite, int $offset): array
    {
        [$where, $p] = self::filtros($f);
        return Database::filas(
            "select b.*, u.nombre || ' ' || u.apellido as usuario, u.correo from bitacora b
             left join usuario u on u.id = b.usuario_id $where
             order by b.fecha desc limit $limite offset $offset",
            $p
        );
    }

    public static function contar(array $f): int
    {
        [$where, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from bitacora b left join usuario u on u.id = b.usuario_id $where", $p);
    }

    public static function recientes(int $n = 6): array
    {
        return Database::filas(
            "select b.accion, b.modulo, b.detalle, b.fecha, u.nombre || ' ' || u.apellido as usuario from bitacora b
             left join usuario u on u.id = b.usuario_id
             where b.accion <> 'LOGIN_FALLIDO' order by b.fecha desc limit $n"
        );
    }

    public static function acciones(): array
    {
        return array_column(Database::filas('select distinct accion from bitacora order by accion'), 'accion');
    }

    public static function modulos(): array
    {
        return array_column(Database::filas('select distinct modulo from bitacora order by modulo'), 'modulo');
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = "(b.detalle ilike :q or (u.nombre || ' ' || u.apellido) ilike :q or u.correo ilike :q)";
            $p['q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['accion'])) {
            $w[] = 'b.accion = :accion';
            $p['accion'] = $f['accion'];
        }
        if (!empty($f['modulo'])) {
            $w[] = 'b.modulo = :modulo';
            $p['modulo'] = $f['modulo'];
        }
        if (!empty($f['desde'])) {
            $w[] = 'b.fecha >= cast(:desde as date)';
            $p['desde'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $w[] = 'b.fecha < cast(:hasta as date) + 1';
            $p['hasta'] = $f['hasta'];
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
