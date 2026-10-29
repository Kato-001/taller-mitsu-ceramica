<?php
/**
 * COMPRA y DETALLE_COMPRA · Compras de materiales a proveedores (CU15).
 * Se registran y anulan con procedimientos almacenados (todo o nada).
 */

declare(strict_types=1);

final class Compra
{
    public static function listar(array $f, int $limite, int $offset): array
    {
        [$where, $p] = self::filtros($f);
        return Database::filas(
            "select c.*, p.nombre as proveedor, u.nombre || ' ' || u.apellido as usuario,
                    (select count(*) from detalle_compra d where d.compra_id = c.id) as items
             from compra c join proveedor p on p.id = c.proveedor_id join usuario u on u.id = c.usuario_id
             $where order by c.fecha desc, c.id desc limit $limite offset $offset",
            $p
        );
    }

    public static function contar(array $f): int
    {
        [$where, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from compra c $where", $p);
    }

    public static function totalMes(): float
    {
        return (float) Database::valor("select coalesce(sum(total), 0) from compra where estado = 'REGISTRADA'
                                         and date_trunc('month', fecha) = date_trunc('month', mitsu.hoy_bo())");
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(
            "select c.*, p.nombre as proveedor, p.nit, p.ciudad, u.nombre || ' ' || u.apellido as usuario
             from compra c join proveedor p on p.id = c.proveedor_id join usuario u on u.id = c.usuario_id where c.id = :id",
            ['id' => $id]
        );
    }

    public static function detalle(int $id): array
    {
        return Database::filas(
            'select d.*, m.nombre as material, un.abreviatura as unidad
             from detalle_compra d join material m on m.id = d.material_id join unidad un on un.id = m.unidad_id
             where d.compra_id = :id order by m.nombre',
            ['id' => $id]
        );
    }

    /** @param array $items [['material_id'=>, 'cantidad'=>, 'costo'=>], …] */
    public static function registrar(int $proveedorId, string $fecha, string $observacion, array $items): int
    {
        return (int) Auth::transaccion(fn() => Database::valor(
            'select mitsu.fn_registrar_compra(:u, :p, cast(:f as date), :o, cast(:i as jsonb))',
            ['u' => Auth::id(), 'p' => $proveedorId, 'f' => $fecha, 'o' => $observacion, 'i' => json_encode(array_values($items))]
        ));
    }

    public static function anular(int $id, string $motivo): void
    {
        Auth::transaccion(fn() => Database::query('select mitsu.fn_anular_compra(:c, :u, :m)',
            ['c' => $id, 'u' => Auth::id(), 'm' => $motivo]));
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['proveedor'])) {
            $w[] = 'c.proveedor_id = :pr';
            $p['pr'] = (int) $f['proveedor'];
        }
        if (!empty($f['estado'])) {
            $w[] = 'c.estado = :e';
            $p['e'] = $f['estado'];
        }
        if (!empty($f['desde'])) {
            $w[] = 'c.fecha >= cast(:d as date)';
            $p['d'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $w[] = 'c.fecha <= cast(:h as date)';
            $p['h'] = $f['hasta'];
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
