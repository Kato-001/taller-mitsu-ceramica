<?php
/**
 * PEDIDO, DETALLE_PEDIDO y PAGO · Compras de la tienda en línea (CU20–CU22).
 */

declare(strict_types=1);

final class Pedido
{
    public const ESTADOS = [
        'PENDIENTE_PAGO'  => ['Esperando pago', 'badge-warn'],
        'EN_VERIFICACION' => ['Pago en verificación', 'badge-info'],
        'PAGADO'          => ['Pagado', 'badge-ok'],
        'ENTREGADO'       => ['Entregado', 'badge-accent'],
        'CANCELADO'       => ['Cancelado', 'badge-off'],
    ];
    public const METODOS = ['QR' => 'QR bancario', 'TARJETA' => 'Tarjeta de débito o crédito', 'PAYPAL' => 'PayPal'];

    private const SELECT = "select p.*, c.nombre as cliente, c.ci_nit, u.correo, u.nombre || ' ' || u.apellido as usuario_nombre,
                                   (select sum(d.cantidad) from detalle_pedido d where d.pedido_id = p.id) as unidades
                            from pedido p join cliente c on c.id = p.cliente_id join usuario u on u.id = p.usuario_id";

    public static function badge(string $estado): string
    {
        [$n, $c] = self::ESTADOS[$estado] ?? [$estado, 'badge-off'];
        return badge($n, $c);
    }

    /** Cliente de la tienda ligado al usuario; se crea la primera vez que compra. */
    public static function clienteDe(array $u): int
    {
        $id = Database::valor('select id from cliente where usuario_id = :u', ['u' => $u['id']]);
        if ($id) {
            return (int) $id;
        }
        return (int) Database::valor(
            'insert into cliente (nombre, telefono, correo, usuario_id) values (:n, :t, :c, :u) returning id',
            ['n' => $u['nombre_completo'], 't' => $u['telefono'] ?: null, 'c' => $u['correo'], 'u' => $u['id']]
        );
    }

    public static function crear(array $u, array $d, array $items): int
    {
        return (int) Auth::transaccion(fn() => Database::valor(
            'select mitsu.fn_crear_pedido(:u, :c, :e, :di, :t, :n, cast(:i as jsonb))',
            ['u' => $u['id'], 'c' => self::clienteDe($u), 'e' => $d['entrega'], 'di' => $d['direccion'],
             't' => $d['telefono'], 'n' => $d['nota'], 'i' => json_encode(array_values($items))]
        ));
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where p.id = :id', ['id' => $id]);
    }

    public static function detalle(int $id): array
    {
        return Database::filas(
            'select d.*, pz.codigo, pz.nombre, pz.imagen, pz.id as pieza_id from detalle_pedido d join pieza pz on pz.id = d.pieza_id
             where d.pedido_id = :id order by pz.nombre',
            ['id' => $id]
        );
    }

    public static function pagos(int $id): array
    {
        return Database::filas(
            "select pg.*, u.nombre || ' ' || u.apellido as verificador from pago pg left join usuario u on u.id = pg.verificado_por
             where pg.pedido_id = :id order by pg.creado_en desc",
            ['id' => $id]
        );
    }

    public static function delUsuario(int $usuarioId): array
    {
        self::vencer();
        return Database::filas(self::SELECT . ' where p.usuario_id = :u order by p.fecha desc', ['u' => $usuarioId]);
    }

    public static function listar(array $f, int $limite, int $offset): array
    {
        self::vencer();
        [$where, $p] = self::filtros($f);
        return Database::filas(self::SELECT . " $where order by p.estado = 'EN_VERIFICACION' desc, p.fecha desc limit $limite offset $offset", $p);
    }

    public static function contar(array $f): int
    {
        [$where, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from pedido p join cliente c on c.id = p.cliente_id $where", $p);
    }

    public static function porEstado(): array
    {
        return array_column(Database::filas('select estado, count(*) n from pedido group by estado'), 'n', 'estado');
    }

    /** Pedidos impagos de más de 30 minutos: se cancelan y vuelve el stock. */
    public static function vencer(): void
    {
        try {
            Database::valor('select mitsu.fn_vencer_pedidos()');
        } catch (Throwable $e) {
            error_log('[Mitsu] No se pudieron vencer pedidos: ' . $e->getMessage());
        }
    }

    public static function registrarPago(int $pedidoId, string $metodo, string $estado, string $referencia, array $extra = []): int
    {
        return (int) Auth::transaccion(fn() => Database::valor(
            'select mitsu.fn_registrar_pago(:p, :m, :e, :r, :ma, :u4, :ti, :cu, :de)',
            ['p' => $pedidoId, 'm' => $metodo, 'e' => $estado, 'r' => $referencia, 'ma' => $extra['marca'] ?? null,
             'u4' => $extra['ultimos4'] ?? null, 'ti' => $extra['titular'] ?? null, 'cu' => $extra['cuenta'] ?? null, 'de' => $extra['detalle'] ?? null]
        ));
    }

    public static function verificarQr(int $pagoId, bool $aprobar, string $detalle): void
    {
        Auth::transaccion(fn() => Database::query('select mitsu.fn_verificar_pago_qr(:p, :u, cast(:a as boolean), :d)',
            ['p' => $pagoId, 'u' => Auth::id(), 'a' => $aprobar ? 'true' : 'false', 'd' => $detalle]));
    }

    public static function cancelar(int $id, string $motivo): void
    {
        Auth::transaccion(fn() => Database::query('select mitsu.fn_cancelar_pedido(:p, :u, :m)', ['p' => $id, 'u' => Auth::id(), 'm' => $motivo]));
    }

    public static function entregar(int $id): void
    {
        Database::query("update pedido set estado = 'ENTREGADO' where id = :id", ['id' => $id]);
    }

    public static function marcarComprobanteEnviado(int $id): void
    {
        Database::query('update pedido set comprobante_enviado = true where id = :id', ['id' => $id]);
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['estado'])) {
            $w[] = 'p.estado = :e';
            $p['e'] = $f['estado'];
        }
        if (!empty($f['q'])) {
            $w[] = "(p.numero ilike '%' || :q || '%' or normalizar(c.nombre) like '%' || normalizar(:q) || '%')";
            $p['q'] = $f['q'];
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
