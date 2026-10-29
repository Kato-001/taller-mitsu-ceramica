<?php
/**
 * VENTA, DETALLE_VENTA y CAJA · Venta en caja, nota de venta, anulación y arqueo (CU18, CU19).
 */

declare(strict_types=1);

final class Venta
{
    // ---------------------------------------------------------------- Caja

    public static function cajaAbierta(): ?array
    {
        return Database::fila(
            "select c.*, u.nombre || ' ' || u.apellido as usuario,
                    (select count(*) from venta v where v.caja_id = c.id and v.estado = 'VALIDA') as ventas,
                    (select coalesce(sum(v.total), 0) from venta v join metodo_pago mp on mp.id = v.metodo_pago_id
                      where v.caja_id = c.id and v.estado = 'VALIDA' and normalizar(mp.nombre) = 'efectivo') as efectivo,
                    (select coalesce(sum(v.total), 0) from venta v join metodo_pago mp on mp.id = v.metodo_pago_id
                      where v.caja_id = c.id and v.estado = 'VALIDA' and normalizar(mp.nombre) <> 'efectivo') as otros
             from caja c join usuario u on u.id = c.usuario_id where c.estado = 'ABIERTA'"
        );
    }

    public static function abrirCaja(string $montoInicial): int
    {
        return (int) Database::valor('insert into caja (usuario_id, monto_inicial) values (:u, cast(:m as numeric)) returning id',
            ['u' => Auth::id(), 'm' => $montoInicial]);
    }

    public static function cerrarCaja(int $id, string $declarado, string $observacion): void
    {
        Auth::transaccion(fn() => Database::query('call mitsu.sp_cerrar_caja(:c, :u, cast(:d as numeric), :o)',
            ['c' => $id, 'u' => Auth::id(), 'd' => $declarado, 'o' => $observacion]));
    }

    public static function cajas(int $limite = 30): array
    {
        return Database::filas(
            "select c.*, u.nombre || ' ' || u.apellido as usuario,
                    (select count(*) from venta v where v.caja_id = c.id and v.estado = 'VALIDA') as ventas
             from caja c join usuario u on u.id = c.usuario_id order by c.abierta_en desc limit $limite"
        );
    }

    // -------------------------------------------------------------- Ventas

    /** @param array $items [['pieza_id'=>, 'cantidad'=>], …] */
    public static function registrar(int $clienteId, int $metodoId, ?string $recibido, array $items): int
    {
        return (int) Auth::transaccion(fn() => Database::valor(
            'select mitsu.fn_registrar_venta(:u, :c, :m, cast(:r as numeric), cast(:i as jsonb))',
            ['u' => Auth::id(), 'c' => $clienteId, 'm' => $metodoId, 'r' => $recibido, 'i' => json_encode(array_values($items))]
        ));
    }

    public static function anular(int $id, string $motivo): void
    {
        Auth::transaccion(fn() => Database::query('select mitsu.fn_anular_venta(:v, :u, :m)',
            ['v' => $id, 'u' => Auth::id(), 'm' => $motivo]));
    }

    public static function listar(array $f, int $limite, int $offset): array
    {
        [$where, $p] = self::filtros($f);
        return Database::filas(
            "select v.*, c.nombre as cliente, mp.nombre as metodo, u.nombre || ' ' || u.apellido as vendedor,
                    (select sum(d.cantidad) from detalle_venta d where d.venta_id = v.id) as unidades
             from venta v join cliente c on c.id = v.cliente_id join metodo_pago mp on mp.id = v.metodo_pago_id
             join usuario u on u.id = v.usuario_id
             $where order by v.fecha desc limit $limite offset $offset",
            $p
        );
    }

    public static function contar(array $f): int
    {
        [$where, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from venta v join cliente c on c.id = v.cliente_id $where", $p);
    }

    public static function totales(array $f): array
    {
        [$where, $p] = self::filtros(['estado' => 'VALIDA'] + $f);
        return Database::fila("select count(*) as ventas, coalesce(sum(v.total), 0) as total from venta v join cliente c on c.id = v.cliente_id $where", $p);
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(
            "select v.*, c.nombre as cliente, c.ci_nit, c.telefono, mp.nombre as metodo,
                    u.nombre || ' ' || u.apellido as vendedor, ua.nombre || ' ' || ua.apellido as anulado_por_nombre
             from venta v join cliente c on c.id = v.cliente_id join metodo_pago mp on mp.id = v.metodo_pago_id
             join usuario u on u.id = v.usuario_id left join usuario ua on ua.id = v.anulado_por where v.id = :id",
            ['id' => $id]
        );
    }

    public static function detalle(int $id): array
    {
        return Database::filas(
            'select d.*, p.codigo, p.nombre from detalle_venta d join pieza p on p.id = d.pieza_id where d.venta_id = :id order by p.nombre',
            ['id' => $id]
        );
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = "(v.numero ilike '%' || :q || '%' or normalizar(c.nombre) like '%' || normalizar(:q) || '%')";
            $p['q'] = $f['q'];
        }
        if (!empty($f['estado'])) {
            $w[] = 'v.estado = :e';
            $p['e'] = $f['estado'];
        }
        if (!empty($f['desde'])) {
            $w[] = "(v.fecha at time zone 'America/La_Paz')::date >= cast(:d as date)";
            $p['d'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $w[] = "(v.fecha at time zone 'America/La_Paz')::date <= cast(:h as date)";
            $p['h'] = $f['hasta'];
        }
        if (!empty($f['caja'])) {
            $w[] = 'v.caja_id = :caja';
            $p['caja'] = (int) $f['caja'];
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
