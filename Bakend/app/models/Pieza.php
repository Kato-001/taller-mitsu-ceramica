<?php
/**
 * PIEZA y MOVIMIENTO_PIEZA · Piezas de cerámica a la venta, fotos y kardex (CU17).
 */

declare(strict_types=1);

final class Pieza
{
    public const TIPOS_MOVIMIENTO = ['PRODUCCION' => 'Producción (piezas terminadas)', 'AJUSTE_MAS' => 'Ajuste de inventario (+)',
                                     'AJUSTE_MENOS' => 'Ajuste de inventario (−) / rotura'];

    private const SELECT = 'select p.*, c.nombre as categoria,
                                   exists (select 1 from pieza_imagen i where i.pieza_id = p.id) as tiene_foto,
                                   (select extract(epoch from i.actualizado_en)::bigint from pieza_imagen i where i.pieza_id = p.id) as foto_version,
                                   (select coalesce(sum(d.cantidad), 0) from detalle_venta d join venta v on v.id = d.venta_id
                                     where d.pieza_id = p.id and v.estado = \'VALIDA\') as vendidas
                            from pieza p join categoria c on c.id = p.categoria_id';

    public static function listar(array $f = []): array
    {
        $w = [];
        $p = [];
        if (!empty($f['q'])) {
            $w[] = "(normalizar(p.nombre) like '%' || normalizar(:q) || '%' or p.codigo ilike :q || '%')";
            $p['q'] = $f['q'];
        }
        if (!empty($f['categoria'])) {
            $w[] = 'p.categoria_id = :c';
            $p['c'] = (int) $f['categoria'];
        }
        if (($f['alerta'] ?? '') === '1') {
            $w[] = "p.stock <= p.stock_minimo and p.estado = 'ACTIVO'";
        }
        if (!empty($f['venta'])) {
            $w[] = "p.estado = 'ACTIVO' and p.stock > 0";
        }
        return Database::filas(self::SELECT . ($w ? ' where ' . implode(' and ', $w) : '') . ' order by p.estado, p.codigo', $p);
    }

    public static function alertas(): array
    {
        return Database::filas(self::SELECT . " where p.estado = 'ACTIVO' and p.stock <= p.stock_minimo order by p.stock, p.nombre");
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where p.id = :id', ['id' => $id]);
    }

    /** URL de la foto: la subida desde el sistema o la estática de /assets. */
    public static function foto(array $p): ?string
    {
        if (!empty($p['tiene_foto'])) {
            return '/piezas/foto?id=' . (int) $p['id'] . '&v=' . (int) ($p['foto_version'] ?? 0);
        }
        return $p['imagen'] ? '/' . ltrim($p['imagen'], '/') : null;
    }

    public static function siguienteCodigo(string $prefijo): string
    {
        $n = (int) Database::valor("select coalesce(max(substring(codigo from '\\d+$')::int), 0) + 1 from pieza where codigo like :p || '-%'",
            ['p' => $prefijo]);
        return sprintf('%s-%03d', $prefijo, $n);
    }

    public static function guardar(?int $id, array $d): int
    {
        $p = ['c' => (int) $d['categoria_id'], 'n' => $d['nombre'], 'd' => $d['descripcion'] ?: null,
              'pr' => $d['precio'], 'min' => (int) $d['stock_minimo']];
        return (int) Auth::transaccion(function () use ($id, $d, $p) {
            if ($id) {
                Database::query('update pieza set categoria_id = :c, nombre = :n, descripcion = :d, precio = cast(:pr as numeric),
                                 stock_minimo = :min where id = :id', $p + ['id' => $id]);
                return $id;
            }
            return Database::valor('insert into pieza (codigo, categoria_id, nombre, descripcion, precio, stock_minimo)
                                    values (:co, :c, :n, :d, cast(:pr as numeric), :min) returning id', $p + ['co' => $d['codigo']]);
        });
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        Database::query('update pieza set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }

    /** La foto viaja en base64: con sentencias emuladas (pooler) no se envían bytes crudos. */
    public static function guardarFoto(int $id, string $mime, string $binario): void
    {
        Database::query(
            "insert into pieza_imagen (pieza_id, tipo_mime, contenido, actualizado_en) values (:p, :m, decode(:b, 'base64'), now())
             on conflict (pieza_id) do update set tipo_mime = excluded.tipo_mime, contenido = excluded.contenido, actualizado_en = now()",
            ['p' => $id, 'm' => $mime, 'b' => base64_encode($binario)]
        );
    }

    public static function leerFoto(int $id): ?array
    {
        $f = Database::fila("select tipo_mime, encode(contenido, 'base64') as b64, actualizado_en from pieza_imagen where pieza_id = :p", ['p' => $id]);
        return $f ? ['mime' => $f['tipo_mime'], 'contenido' => base64_decode($f['b64']), 'fecha' => $f['actualizado_en']] : null;
    }

    public static function movimiento(int $id, string $tipo, int $cantidad, string $motivo): void
    {
        Auth::transaccion(fn() => Database::query(
            'insert into movimiento_pieza (pieza_id, tipo, cantidad, motivo, usuario_id) values (:p, :t, :c, :m, :u)',
            ['p' => $id, 't' => $tipo, 'c' => $cantidad, 'm' => $motivo, 'u' => Auth::id()]
        ));
    }

    public static function kardex(int $id, int $limite = 100): array
    {
        return Database::filas(
            "select mp.*, u.nombre || ' ' || u.apellido as usuario, v.numero as venta
             from movimiento_pieza mp join usuario u on u.id = mp.usuario_id left join venta v on v.id = mp.venta_id
             where mp.pieza_id = :id order by mp.fecha desc, mp.id desc limit $limite",
            ['id' => $id]
        );
    }

    public static function historialPrecios(int $id): array
    {
        return Database::filas(
            "select h.*, u.nombre || ' ' || u.apellido as usuario from historial_precio h
             left join usuario u on u.id = h.usuario_id where h.pieza_id = :id order by h.fecha desc",
            ['id' => $id]
        );
    }
}
