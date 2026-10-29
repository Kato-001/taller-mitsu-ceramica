<?php
/**
 * CU15 · Detalle de una compra y anulación con motivo
 * @var array $compra @var array $detalle
 */
$c = $compra;
$titulo = 'Compra ' . $c['numero'];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/compras"><?= icono('izquierda', 14) ?> Compras</a></div>
        <h1>Compra <?= e($c['numero']) ?></h1>
        <p><?= e(fecha($c['fecha'])) ?> · <?= e($c['proveedor']) ?> · <?= badge_estado_op($c['estado']) ?></p>
    </div>
    <div class="row">
        <button class="btn btn-outline no-print" onclick="window.print()"><?= icono('imprimir') ?> Imprimir</button>
        <?php if ($c['estado'] === 'REGISTRADA'): ?>
            <form method="post" action="/compras/anular" class="inline" data-confirm="Anular la compra <?= e($c['numero']) ?>" data-confirm-texto="Se descontará del stock lo que ingresó. Si el material ya se consumió, no se podrá anular." data-motivo="Motivo de la anulación" data-confirm-ok="Anular compra" data-peligro>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn btn-ghost btn-danger-ghost"><?= icono('x-circulo') ?> Anular</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($c['estado'] === 'ANULADA'): ?>
    <div class="alert alert-error anim"><?= icono('alerta') ?><div>Compra anulada. Motivo: <?= e($c['motivo_anulacion']) ?></div></div>
<?php endif; ?>

<div class="split" data-escalonar>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Material</th><th style="text-align:right">Cantidad</th><th style="text-align:right">Costo unit.</th><th style="text-align:right">Subtotal</th></tr></thead>
                <tbody>
                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td><a href="<?= e(url('/materiales/kardex', ['id' => $d['material_id']])) ?>"><?= e($d['material']) ?></a></td>
                        <td style="text-align:right" class="mono"><?= cantidad($d['cantidad']) ?> <?= e($d['unidad']) ?></td>
                        <td style="text-align:right" class="mono"><?= bs($d['costo_unitario']) ?></td>
                        <td style="text-align:right" class="mono"><strong><?= bs($d['subtotal']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row"><td colspan="3" style="text-align:right"><strong>Total</strong></td><td style="text-align:right" class="mono"><strong><?= bs($c['total']) ?></strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card card-pad">
        <div class="kv-list">
            <div class="kv"><span>Proveedor</span><strong><?= e($c['proveedor']) ?></strong></div>
            <div class="kv"><span>NIT</span><strong class="mono"><?= e($c['nit'] ?: '—') ?></strong></div>
            <div class="kv"><span>Ciudad</span><strong><?= e($c['ciudad']) ?></strong></div>
            <div class="kv"><span>Registrada por</span><strong><?= e($c['usuario']) ?></strong></div>
            <div class="kv"><span>Registrada el</span><strong><?= e(fecha_hora($c['creado_en'])) ?></strong></div>
            <div class="kv"><span>Observación</span><strong><?= e($c['observacion'] ?: '—') ?></strong></div>
        </div>
    </div>
</div>
