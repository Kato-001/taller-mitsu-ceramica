<?php
/**
 * MATERIAL y MOVIMIENTO_MATERIAL · Insumos y su kardex (CU13, CU16).
 * El stock nunca se edita a mano: cambia solo con compras y movimientos.
 */

declare(strict_types=1);

final class Material
{
    public const TIPOS_SALIDA = ['CONSUMO' => 'Consumo en clases o producción', 'MERMA' => 'Merma (roto, vencido, perdido)',
                                 'AJUSTE_MAS' => 'Ajuste de inventario (+)', 'AJUSTE_MENOS' => 'Ajuste de inventario (−)'];

    private const SELECT = 'select m.*, c.nombre as categoria, u.abreviatura as unidad, u.nombre as unidad_nombre,
                                   round(m.stock * m.costo_promedio, 2) as valor
                            from material m
                            join categoria c on c.id = m.categoria_id
                            join unidad u on u.id = m.unidad_id';

    public static function listar(array $f = []): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = "normalizar(m.nombre) like '%' || normalizar(:q) || '%'";
            $p['q'] = $f['q'];
        }
        if (!empty($f['categoria'])) {
            $w[] = 'm.categoria_id = :c';
            $p['c'] = (int) $f['categoria'];
        }
        if (($f['alerta'] ?? '') === '1') {
            $w[] = "m.stock <= m.stock_minimo and m.estado = 'ACTIVO'";
        }
        return Database::filas(self::SELECT . ($w ? ' where ' . implode(' and ', $w) : '')
            . ' order by m.estado, (m.stock <= m.stock_minimo) desc, m.nombre', $p);
    }

    public static function activos(): array
    {
        return Database::filas(self::SELECT . " where m.estado = 'ACTIVO' order by m.nombre");
    }

    /** Materiales en o bajo el stock mínimo (alertas, CU16). */
    public static function alertas(): array
    {
        return Database::filas(self::SELECT . " where m.estado = 'ACTIVO' and m.stock <= m.stock_minimo order by (m.stock / nullif(m.stock_minimo, 0)), m.nombre");
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where m.id = :id', ['id' => $id]);
    }

    public static function guardar(?int $id, array $d): int
    {
        $p = ['c' => (int) $d['categoria_id'], 'u' => (int) $d['unidad_id'], 'n' => $d['nombre'],
              'd' => $d['descripcion'] ?: null, 'min' => $d['stock_minimo']];
        if ($id) {
            Database::query('update material set categoria_id = :c, unidad_id = :u, nombre = :n, descripcion = :d,
                             stock_minimo = cast(:min as numeric) where id = :id', $p + ['id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into material (categoria_id, unidad_id, nombre, descripcion, stock_minimo)
                                      values (:c, :u, :n, :d, cast(:min as numeric)) returning id', $p);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        Database::query('update material set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }

    /** Registra consumo, merma o ajuste. El trigger del kardex valida que no quede stock negativo. */
    public static function movimiento(int $id, string $tipo, string $cantidad, string $motivo): void
    {
        Auth::transaccion(fn() => Database::query(
            'insert into movimiento_material (material_id, tipo, cantidad, motivo, usuario_id)
             values (:m, :t, cast(:c as numeric), :mo, :u)',
            ['m' => $id, 't' => $tipo, 'c' => $cantidad, 'mo' => $motivo, 'u' => Auth::id()]
        ));
    }

    public static function kardex(int $id, int $limite = 100): array
    {
        return Database::filas(
            "select mm.*, u.nombre || ' ' || u.apellido as usuario, c.numero as compra
             from movimiento_material mm join usuario u on u.id = mm.usuario_id
             left join compra c on c.id = mm.compra_id
             where mm.material_id = :id order by mm.fecha desc, mm.id desc limit $limite",
            ['id' => $id]
        );
    }
}
