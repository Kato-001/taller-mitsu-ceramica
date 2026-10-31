<?php
/**
 * Comprobante del pedido web (lo ven el cliente y el personal)
 * @var array $pedido @var array $detalle @var array $pagos
 */
$p = $pedido;
$titulo = 'Pedido ' . $p['numero'];
$aprobado = array_values(array_filter($pagos, fn($x) => $x['estado'] === 'APROBADO'))[0] ?? null;
$pendiente = array_values(array_filter($pagos, fn($x) => $x['estado'] === 'PENDIENTE'))[0] ?? null;
?>
<div class="page-head no-print">
    <div><div class="eyebrow"><a href="/mis-pedidos"><?= icono('izquierda', 14) ?> Mis pedidos</a></div><h1>Pedido <?= e($p['numero']) ?></h1><p><?= Pedido::badge($p['estado']) ?></p></div>
    <div class="row">
        <?php if ($p['estado'] === 'PENDIENTE_PAGO'): ?><a class="btn btn-primary" href="<?= e(url('/pago', ['pedido' => $p['id']])) ?>"><?= icono('candado') ?> Pagar ahora</a><?php endif; ?>
        <button class="btn btn-outline" onclick="window.print()"><?= icono('imprimir') ?> Imprimir</button>
    </div>
</div>
<?php if ($p['estado'] === 'EN_VERIFICACION'): ?>
    <div class="alert alert-info anim no-print"><?= icono('reloj') ?><div>Recibimos tu aviso de pago por QR. El taller está verificando el depósito; el comprobante se activa al confirmarlo.</div></div>
<?php endif; ?>
<div class="card card-pad nota anim">
    <div class="nota-head">
        <div class="brand" style="padding:0"><span class="brand-logo"><?= icono('vasija', 20) ?></span><span><strong><?= e(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü')) ?></strong><small><?= e(config('CIUDAD')) ?></small></span></div>
        <div style="text-align:right"><h2 style="font-size:19px"><?= in_array($p['estado'], ['PAGADO', 'ENTREGADO'], true) ? 'COMPROBANTE DE PAGO' : 'RESUMEN DE PEDIDO' ?></h2>
            <div class="mono" style="font-size:17px;font-weight:700"><?= e($p['numero']) ?></div><small class="faint"><?= e(date('d/m/Y H:i', strtotime($p['fecha']))) ?></small></div>
    </div>
    <?php if ($p['estado'] === 'CANCELADO'): ?><div style="text-align:center;margin-bottom:12px"><span class="sello">CANCELADO</span><p class="soft" style="font-size:13px"><?= e($p['motivo_cancelacion']) ?></p></div><?php endif; ?>
    <div class="kv-list" style="margin-bottom:14px">
        <div class="kv"><span>Cliente</span><strong><?= e($p['cliente']) ?></strong></div>
        <div class="kv"><span>Correo</span><strong><?= e($p['correo']) ?></strong></div>
        <div class="kv"><span>Entrega</span><strong><?= $p['entrega'] === 'RETIRO' ? 'Retiro en el taller' : 'Envío: ' . e($p['direccion']) ?></strong></div>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Cant.</th><th>Pieza</th><th style="text-align:right">P. unit.</th><th style="text-align:right">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($detalle as $d): ?>
            <tr><td><?= (int) $d['cantidad'] ?></td><td><?= e($d['nombre']) ?> <span class="faint mono" style="font-size:12px"><?= e($d['codigo']) ?></span></td>
                <td style="text-align:right" class="mono"><?= bs($d['precio_unitario']) ?></td><td style="text-align:right" class="mono"><?= bs($d['subtotal']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="3" style="text-align:right"><strong>TOTAL</strong></td><td style="text-align:right" class="mono"><strong><?= bs($p['total']) ?></strong></td></tr>
        </tbody>
    </table></div>
    <?php if ($aprobado || $pendiente): $x = $aprobado ?? $pendiente; ?>
        <div class="kv-list mt">
            <div class="kv"><span>Método de pago</span><strong><?= e(Pedido::METODOS[$x['metodo']]) ?><?= $x['tarjeta_ultimos4'] ? ' · ' . e($x['tarjeta_marca']) . ' •••• ' . e($x['tarjeta_ultimos4']) : '' ?><?= $x['cuenta_paypal'] ? ' · ' . e($x['cuenta_paypal']) : '' ?></strong></div>
            <div class="kv"><span>Referencia</span><strong class="mono"><?= e($x['referencia']) ?></strong></div>
            <div class="kv"><span>Estado del pago</span><strong><?= badge_estado_op($x['estado']) ?></strong></div>
            <?php if ($p['pagado_en']): ?><div class="kv"><span>Pagado el</span><strong><?= e(fecha_hora($p['pagado_en'])) ?></strong></div><?php endif; ?>
        </div>
    <?php endif; ?>
    <p class="faint" style="font-size:12px;text-align:center;margin:18px 0 0">Comprobante de compra en línea · Pasarela en modo de demostración (sin cobro real) · Sin valor fiscal.</p>
</div>
