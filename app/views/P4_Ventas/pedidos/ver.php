<?php
/**
 * CU22 · Detalle de un pedido web: verificar pago QR, entregar o cancelar
 * @var array $pedido @var array $detalle @var array $pagos
 */
$p = $pedido;
$titulo = 'Pedido ' . $p['numero'];
?>
<div class="page-head">
    <div><div class="eyebrow"><a href="/pedidos"><?= icono('izquierda', 14) ?> Pedidos web</a></div><h1>Pedido <?= e($p['numero']) ?></h1>
        <p><?= Pedido::badge($p['estado']) ?> · <?= e(fecha_hora($p['fecha'])) ?> · <?= e($p['cliente']) ?></p></div>
    <div class="row">
        <a class="btn btn-outline" href="<?= e(url('/mis-pedidos/ver', ['id' => $p['id']])) ?>"><?= icono('recibo') ?> Comprobante</a>
        <?php if ($p['estado'] === 'PAGADO'): ?>
            <form method="post" action="/pedidos/entregar" class="inline" data-confirm="Marcar como entregado" data-confirm-texto="El cliente ya recibió sus piezas." data-confirm-ok="Entregado">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-primary"><?= icono('check') ?> Entregado</button></form>
        <?php endif; ?>
        <?php if (!in_array($p['estado'], ['ENTREGADO', 'CANCELADO'], true)): ?>
            <form method="post" action="/pedidos/cancelar" class="inline" data-confirm="Cancelar el pedido" data-confirm-texto="Las piezas vuelven al stock.<?= $p['estado'] === 'PAGADO' ? ' Debe devolver el dinero al cliente.' : '' ?>" data-motivo="Motivo" data-confirm-ok="Cancelar pedido" data-peligro>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-ghost btn-danger-ghost"><?= icono('x-circulo') ?> Cancelar</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="split" data-escalonar>
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Piezas</h2><strong class="mono"><?= bs($p['total']) ?></strong></div>
            <div class="table-wrap"><table class="table"><tbody>
                <?php foreach ($detalle as $d): ?>
                    <tr><td><?= (int) $d['cantidad'] ?> ×</td><td><a href="<?= e(url('/piezas/kardex', ['id' => $d['pieza_id']])) ?>"><?= e($d['nombre']) ?></a></td><td style="text-align:right" class="mono"><?= bs($d['subtotal']) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Intentos de pago</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Fecha</th><th>Método</th><th>Referencia</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pagos as $x): ?>
                    <tr>
                        <td style="white-space:nowrap"><?= e(fecha_hora($x['creado_en'])) ?></td>
                        <td><?= e(Pedido::METODOS[$x['metodo']]) ?><?= $x['tarjeta_ultimos4'] ? '<div class="faint" style="font-size:12px">' . e($x['tarjeta_marca']) . ' •••• ' . e($x['tarjeta_ultimos4']) . '</div>' : '' ?></td>
                        <td class="mono" style="font-size:12.5px"><?= e($x['referencia']) ?><div class="faint" style="font-family:var(--font)"><?= e($x['detalle']) ?></div></td>
                        <td><?= badge_estado_op($x['estado']) ?><?= $x['verificador'] ? '<div class="faint" style="font-size:12px">' . e($x['verificador']) . '</div>' : '' ?></td>
                        <td><?php if ($x['metodo'] === 'QR' && $x['estado'] === 'PENDIENTE'): ?>
                            <div class="row" style="gap:6px;flex-wrap:nowrap">
                                <form method="post" action="/pedidos/verificar-qr" class="inline" data-confirm="Confirmar depósito" data-confirm-texto="Revisó en el banco que llegaron <?= bs($p['total']) ?> con la glosa <?= e($p['numero']) ?>." data-confirm-ok="Confirmar pago">
                                    <?= csrf_field() ?><input type="hidden" name="pago_id" value="<?= $x['id'] ?>"><input type="hidden" name="pedido_id" value="<?= $p['id'] ?>"><input type="hidden" name="decision" value="aprobar">
                                    <button class="btn btn-primary btn-sm" data-sin-carga="1"><?= icono('check', 15) ?> Confirmar</button></form>
                                <form method="post" action="/pedidos/verificar-qr" class="inline" data-confirm="Rechazar el pago" data-motivo="¿Por qué se rechaza?" data-confirm-ok="Rechazar" data-peligro>
                                    <?= csrf_field() ?><input type="hidden" name="pago_id" value="<?= $x['id'] ?>"><input type="hidden" name="pedido_id" value="<?= $p['id'] ?>"><input type="hidden" name="decision" value="rechazar">
                                    <button class="btn btn-ghost btn-sm btn-danger-ghost" data-sin-carga="1">Rechazar</button></form>
                            </div>
                        <?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table><?php if (!$pagos): ?><div class="empty">El cliente aún no intentó pagar.</div><?php endif; ?></div>
        </div>
    </div>
    <div class="card card-pad">
        <div class="kv-list">
            <div class="kv"><span>Cliente</span><strong><?= e($p['cliente']) ?></strong></div>
            <div class="kv"><span>Correo</span><strong><?= e($p['correo']) ?></strong></div>
            <div class="kv"><span>Teléfono</span><strong><?= e($p['telefono']) ?></strong></div>
            <div class="kv"><span>Entrega</span><strong><?= $p['entrega'] === 'RETIRO' ? 'Retiro en el taller' : e($p['direccion']) ?></strong></div>
            <div class="kv"><span>Nota</span><strong><?= e($p['nota'] ?: '—') ?></strong></div>
            <div class="kv"><span>Comprobante por correo</span><strong><?= $p['comprobante_enviado'] ? 'Enviado' : 'Pendiente (correo no configurado)' ?></strong></div>
            <?php if ($p['motivo_cancelacion']): ?><div class="kv"><span>Motivo de cancelación</span><strong><?= e($p['motivo_cancelacion']) ?></strong></div><?php endif; ?>
        </div>
    </div>
</div>
