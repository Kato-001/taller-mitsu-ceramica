<?php
/**
 * PROVEEDOR · Empresas que venden arcilla, minerales y esmaltes al taller (CU14).
 */

declare(strict_types=1);

final class Proveedor
{
    public static function todos(bool $soloActivos = false): array
    {
        return Database::filas(
            "select p.*, (select count(*) from compra c where c.proveedor_id = p.id and c.estado = 'REGISTRADA') as compras,
                    (select coalesce(sum(c.total), 0) from compra c where c.proveedor_id = p.id and c.estado = 'REGISTRADA') as total_comprado
             from proveedor p " . ($soloActivos ? "where p.estado = 'ACTIVO' " : '') . 'order by p.estado, p.nombre'
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila('select * from proveedor where id = :id', ['id' => $id]);
    }

    public static function guardar(?int $id, array $d): int
    {
        $p = ['n' => $d['nombre'], 'nit' => $d['nit'] ?: null, 'co' => $d['contacto'] ?: null, 't' => $d['telefono'] ?: null,
              'c' => $d['correo'] ?: null, 'ci' => $d['ciudad'], 'di' => $d['direccion'] ?: null];
        if ($id) {
            Database::query('update proveedor set nombre = :n, nit = :nit, contacto = :co, telefono = :t, correo = :c,
                             ciudad = :ci, direccion = :di where id = :id', $p + ['id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into proveedor (nombre, nit, contacto, telefono, correo, ciudad, direccion)
                                      values (:n, :nit, :co, :t, :c, :ci, :di) returning id', $p);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        Database::query('update proveedor set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }
}
