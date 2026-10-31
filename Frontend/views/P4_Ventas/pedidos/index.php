<?php
/**
 * CU22 · Pedidos de la tienda en línea (personal del taller)
 * @var array $pedidos @var array $conteo @var array $f @var Paginador $pag
 */
$titulo = 'Pedidos web';
?>
<div class="page-head">
    <div><div class="eyebrow"><?= icono('paquete', 14) ?> Ventas</div><h1>Pedidos de la tienda en línea</h1>
        <p>Confirme los pagos por QR, entregue los pedidos pagados o cancele con motivo (el stock vuelve).</p></div>
    <a href="/tienda" class="btn btn-outline" target="_blank" rel="noopener"><?= icono('tienda') ?> Ver tienda</a>
</div>
<?php if (!empty($conteo['EN_VERIFICACION'])): ?>
    <div class="alert alert-warn anim"><?= icono('alerta') ?><div><strong><?= (int) $conteo['EN_VERIFICACION'] ?> pago(s) por QR</strong> esperan que confirme el depósito. <a href="/pedidos?estado=EN_VERIFICACION">Ver ahora</a></div></div>
<?php endif; ?>
<div class="card anim">
    <form class="toolbar" method="get">
        <div class="input-icon"><?= icono('buscar') ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="N.º de pedido o cliente" aria-label="Buscar"></div>
        <div class="pill-tabs">
            <a href="/pedidos" class="<?= $f['estado'] === '' ? 'on' : '' ?>">Todos</a>
            <?php foreach (Pedido::ESTADOS as $k => [$n]): ?>
                <a href="<?= e(url('/pedidos', ['estado' => $k])) ?>" class="<?= $f['estado'] === $k ? 'on' : '' ?>"><?= e($n) ?> <span class="n"><?= (int) ($conteo[$k] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Entrega</th><th style="text-align:right">Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td class="mono"><a href="<?= e(url('/pedidos/ver', ['id' => $p['id']])) ?>"><?= e($p['numero']) ?></a></td>
                    <td style="white-space:nowrap"><?= e(fecha_hora($p['fecha'])) ?></td>
                    <td><?= e($p['cliente']) ?><div class="faint" style="font-size:12px"><?= e($p['correo']) ?></div></td>
                    <td><?= $p['entrega'] === 'RETIRO' ? 'Retiro' : 'Envío' ?></td>
                    <td style="text-align:right" class="mono"><strong><?= bs($p['total']) ?></strong></td>
                    <td><?= Pedido::badge($p['estado']) ?></td>
                    <td><a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/pedidos/ver', ['id' => $p['id']])) ?>" title="Ver"><?= icono('ver', 16) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$pedidos): ?><div class="empty"><?= icono('paquete', 36) ?><div><strong>No hay pedidos con ese filtro</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
