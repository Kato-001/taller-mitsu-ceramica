<?php
/**
 * CONFIGURACION y catálogos base: categorías, unidades y métodos de pago (CU06).
 */

declare(strict_types=1);

final class Configuracion
{
    /** Reglas de validación de los parámetros numéricos: clave => [mín, máx]. */
    public const REGLAS = [
        'HORAS_CANCELACION'  => [0, 72],
        'SEMANAS_CALENDARIO' => [1, 12],
    ];

    /** Tablas de catálogo editables desde la pantalla de configuración. */
    public const CATALOGOS = [
        'categoria'   => 'Categorías',
        'unidad'      => 'Unidades de medida',
        'metodo_pago' => 'Métodos de pago',
    ];

    /** @return array<string,string> */
    public static function todas(): array
    {
        return array_column(Database::filas('select clave, valor from configuracion'), 'valor', 'clave');
    }

    public static function listar(): array
    {
        return Database::filas('select * from configuracion order by clave');
    }

    public static function actualizar(string $clave, string $valor): void
    {
        Database::query('update configuracion set valor = :v where clave = :c', ['v' => $valor, 'c' => $clave]);
    }

    public static function categorias(?string $tipo = null, bool $soloActivas = false): array
    {
        return Database::filas(
            "select c.*, (select count(*) from material m where m.categoria_id = c.id)
                       + (select count(*) from pieza p where p.categoria_id = c.id) as usos
             from categoria c
             where (cast(:t as varchar) is null or c.tipo = :t) " . ($soloActivas ? "and c.estado = 'ACTIVO'" : '') . '
             order by c.tipo, c.nombre',
            ['t' => $tipo]
        );
    }

    public static function unidades(bool $soloActivas = false): array
    {
        return Database::filas('select u.*, (select count(*) from material m where m.unidad_id = u.id) as usos from unidad u '
            . ($soloActivas ? "where u.estado = 'ACTIVO' " : '') . 'order by u.nombre');
    }

    public static function metodosPago(bool $soloActivos = false): array
    {
        return Database::filas('select mp.*, (select count(*) from venta v where v.metodo_pago_id = mp.id) as usos from metodo_pago mp '
            . ($soloActivos ? "where mp.estado = 'ACTIVO' " : '') . 'order by mp.id');
    }

    public static function guardarCategoria(?int $id, string $nombre, string $tipo): int
    {
        if ($id) {
            Database::query('update categoria set nombre = :n where id = :id', ['n' => $nombre, 'id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into categoria (nombre, tipo) values (:n, :t) returning id', ['n' => $nombre, 't' => $tipo]);
    }

    public static function guardarUnidad(?int $id, string $nombre, string $abreviatura): int
    {
        if ($id) {
            Database::query('update unidad set nombre = :n, abreviatura = :a where id = :id', ['n' => $nombre, 'a' => $abreviatura, 'id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into unidad (nombre, abreviatura) values (:n, :a) returning id', ['n' => $nombre, 'a' => $abreviatura]);
    }

    public static function guardarMetodo(?int $id, string $nombre): int
    {
        if ($id) {
            Database::query('update metodo_pago set nombre = :n where id = :id', ['n' => $nombre, 'id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into metodo_pago (nombre) values (:n) returning id', ['n' => $nombre]);
    }

    /** Activa o desactiva un elemento de catálogo (nunca se borra: puede tener historial). */
    public static function cambiarEstadoCatalogo(string $tabla, int $id): ?string
    {
        if (!isset(self::CATALOGOS[$tabla])) {
            return null;
        }
        // $tabla viene de una lista cerrada, no del usuario
        return Database::valor(
            "update $tabla set estado = case when estado = 'ACTIVO' then 'INACTIVO' else 'ACTIVO' end where id = :id returning estado",
            ['id' => $id]
        );
    }
}
