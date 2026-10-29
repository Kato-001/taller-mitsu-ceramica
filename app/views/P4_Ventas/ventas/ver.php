<?php
/**
 * CU18 · Nota de venta (comprobante imprimible) y anulación con motivo
 * @var array $venta @var array $detalle
 */
$v = $venta;
$titulo = 'Nota de venta ' . $v['numero'];
$cambio = $v['monto_recibido'] !== null ? (float) $v['monto_recibido'] - (float) $v['total'] : 0;
?>
<div class="page-head no-print">
    <div>
        <div class="eyebrow"><a href="/ventas"><?= icono('izquierda', 14) ?> Ventas</a></div>
        <h1>Nota de venta <?= e($v['numero']) ?></h1>
        <p><?= badge_estado_op($v['estado']) ?> · <?= e(fecha_hora($v['fecha'])) ?></p>
    </div>
    <div class="row">
        <button class="btn btn-outline" onclick="window.print()"><?= icono('imprimir') ?> Imprimir</button>
        <?php if (Auth::puede('ventas.registrar')): ?><a href="/ventas/nueva" class="btn btn-primary"><?= icono('carrito') ?> Nueva venta</a><?php endif; ?>
    </div>
</div>

<div class="card card-pad nota anim">
    <div class="nota-head">
        <div>
            <div class="brand" style="padding:0"><span class="brand-logo"><?= icono('vasija', 20) ?></span><span><strong><?= e(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü')) ?></strong><small><?= e(config('CIUDAD')) ?> · Tel. <?= e(config('TELEFONO')) ?></small></span></div>
        </div>
        <div style="text-align:right">
            <h2 style="font-size:20px">NOTA DE VENTA</h2>
            <div class="mono" style="font-size:18px;font-weight:700"><?= e($v['numero']) ?></div>
            <small class="faint"><?= e(date('d/m/Y H:i', strtotime($v['fecha']))) ?></small>
        </div>
    </div>
    <?php if ($v['estado'] === 'ANULADA'): ?>
        <div style="text-align:center;margin:6px 0 14px"><span class="sello">ANULADA</span>
            <p class="soft" style="font-size:13px;margin:10px 0 0">Motivo: <?= e($v['motivo_anulacion']) ?> · <?= e($v['anulado_por_nombre']) ?> · <?= e(fecha_hora($v['anulado_en'])) ?></p></div>
    <?php endif; ?>
    <div class="kv-list" style="margin-bottom:14px">
        <div class="kv"><span>Cliente</span><strong><?= e($v['cliente']) ?></strong></div>
        <div class="kv"><span>CI / NIT</span><strong class="mono"><?= e($v['ci_nit'] ?: 'S/N') ?></strong></div>
        <div class="kv"><span>Atendido por</span><strong><?= e($v['vendedor']) ?></strong></div>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Cant.</th><th>Descripción</th><th style="text-align:right">P. unit.</th><th style="text-align:right">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($detalle as $d): ?>
            <tr><td><?= (int) $d['cantidad'] ?></td><td><?= e($d['nombre']) ?> <span class="faint mono" style="font-size:12px"><?= e($d['codigo']) ?></span></td>
                <td style="text-align:right" class="mono"><?= bs($d['precio_unitario']) ?></td><td style="text-align:right" class="mono"><?= bs($d['subtotal']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="3" style="text-align:right"><strong>TOTAL</strong></td><td style="text-align:right" class="mono"><strong style="font-size:17px"><?= bs($v['total']) ?></strong></td></tr>
        </tbody>
    </table></div>
    <div class="kv-list mt">
        <div class="kv"><span>Método de pago</span><strong><?= e($v['metodo']) ?></strong></div>
        <?php if ($v['monto_recibido'] !== null): ?>
            <div class="kv"><span>Recibido</span><strong><?= bs($v['monto_recibido']) ?></strong></div>
            <?php if ($cambio > 0): ?><div class="kv"><span>Cambio</span><strong><?= bs($cambio) ?></strong></div><?php endif; ?>
        <?php endif; ?>
    </div>
    <p class="faint" style="font-size:12px;text-align:center;margin:18px 0 0">Gracias por llevar una pieza hecha a mano. · Documento sin valor fiscal (nota de venta interna).</p>
</div>

<?php if ($v['estado'] === 'VALIDA' && Auth::puede('ventas.anular')): ?>
    <form method="post" action="/ventas/anular" class="no-print" style="max-width:640px;margin:16px auto 0;text-align:right"
          data-confirm="Anular la venta <?= e($v['numero']) ?>" data-confirm-texto="Las piezas vuelven al stock y la venta deja de sumar en caja. Queda registrada en la bitácora." data-motivo="Motivo de la anulación" data-confirm-ok="Anular venta" data-peligro>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>">
        <button class="btn btn-ghost btn-danger-ghost"><?= icono('x-circulo') ?> Anular venta</button>
    </form>
<?php endif; ?>
