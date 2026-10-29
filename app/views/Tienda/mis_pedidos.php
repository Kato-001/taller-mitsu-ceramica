<?php
/**
 * Mis pedidos (cliente o alumno que compró en la tienda)
 * @var array $pedidos
 */
$titulo = 'Mis pedidos';
?>
<div class="page-head"><div><div class="eyebrow"><?= icono('recibo', 14) ?> Mi cuenta</div><h1>Mis pedidos</h1><p>Tus compras en la tienda y sus comprobantes.</p></div>
    <a href="/tienda" class="btn btn-outline"><?= icono('tienda') ?> Seguir comprando</a></div>
<div class="card anim">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Pedido</th><th>Fecha</th><th>Piezas</th><th style="text-align:right">Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td class="mono"><a href="<?= e(url('/mis-pedidos/ver', ['id' => $p['id']])) ?>"><?= e($p['numero']) ?></a></td>
                    <td><?= e(fecha_hora($p['fecha'])) ?></td>
                    <td><?= (int) $p['unidades'] ?></td>
                    <td style="text-align:right" class="mono"><strong><?= bs($p['total']) ?></strong></td>
                    <td><?= Pedido::badge($p['estado']) ?></td>
                    <td><?= $p['estado'] === 'PENDIENTE_PAGO'
                        ? '<a class="btn btn-primary btn-sm" href="' . e(url('/pago', ['pedido' => $p['id']])) . '">Pagar</a>'
                        : '<a class="btn btn-ghost btn-sm" href="' . e(url('/mis-pedidos/ver', ['id' => $p['id']])) . '">' . icono('recibo', 15) . ' Comprobante</a>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$pedidos): ?><div class="empty"><?= icono('carrito', 36) ?><div><strong>Aún no tienes pedidos</strong></div><a href="/tienda" class="btn btn-primary btn-sm" style="margin-top:10px">Ir a la tienda</a></div><?php endif; ?>
    </div>
</div>
