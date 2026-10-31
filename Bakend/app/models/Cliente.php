<?php
/**
 * CLIENTE · Compradores de piezas (parte de CU18). "Cliente general" es la venta sin datos.
 */

declare(strict_types=1);

final class Cliente
{
    public static function listar(string $q = ''): array
    {
        return Database::filas(
            "select c.*, (select count(*) from venta v where v.cliente_id = c.id and v.estado = 'VALIDA') as compras,
                    (select coalesce(sum(v.total), 0) from venta v where v.cliente_id = c.id and v.estado = 'VALIDA') as total
             from cliente c
             where (:q = '' or normalizar(c.nombre) like '%' || normalizar(:q) || '%' or c.ci_nit like :q || '%')
             order by c.id = 1 desc, c.estado, c.nombre",
            ['q' => $q]
        );
    }

    public static function activos(): array
    {
        return Database::filas("select id, nombre, ci_nit from cliente where estado = 'ACTIVO' order by id = 1 desc, nombre");
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila('select * from cliente where id = :id', ['id' => $id]);
    }

    public static function guardar(?int $id, array $d): int
    {
        $p = ['n' => $d['nombre'], 'ci' => $d['ci_nit'] ?: null, 't' => $d['telefono'] ?: null, 'c' => $d['correo'] ?: null];
        if ($id) {
            Database::query('update cliente set nombre = :n, ci_nit = :ci, telefono = :t, correo = :c where id = :id', $p + ['id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into cliente (nombre, ci_nit, telefono, correo) values (:n, :ci, :t, :c) returning id', $p);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        if ($id === 1) {
            throw new AppException('El cliente general no se puede desactivar.');
        }
        Database::query('update cliente set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }
}
