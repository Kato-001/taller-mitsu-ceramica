<?php
/**
 * CU19 · Abrir y cerrar caja con arqueo
 * @var ?array $caja @var array $ventas @var array $cajas
 */
$titulo = 'Caja';
$esperado = $caja ? (float) $caja['monto_inicial'] + (float) $caja['efectivo'] : 0;
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('caja', 14) ?> Ventas</div>
        <h1>Caja</h1>
        <p>Solo hay una caja abierta a la vez. Al cerrar, se compara el efectivo contado con el esperado (arqueo).</p>
    </div>
    <?php if ($caja && Auth::puede('ventas.registrar')): ?><a href="/ventas/nueva" class="btn btn-primary"><?= icono('carrito') ?> Vender</a><?php endif; ?>
</div>

<?php if (!$caja): ?>
    <form method="post" action="/caja/abrir" class="card card-pad anim stack" style="max-width:480px" data-validar>
        <?= csrf_field() ?>
        <h2 style="font-size:18px"><?= icono('caja', 19) ?> Abrir caja</h2>
        <label class="field"><span>Monto inicial en efectivo (Bs) *</span>
            <input class="input<?= clase_error('monto_inicial') ?>" type="number" name="monto_inicial" value="<?= e(old('monto_inicial', '200')) ?>" required min="0" max="100000" step="0.5" autofocus>
            <?= error_campo('monto_inicial') ?: '<small>El cambio con el que empieza el día.</small>' ?></label>
        <button class="btn btn-primary"><?= icono('check') ?> Abrir caja</button>
    </form>
<?php else: ?>
    <div class="stats" data-escalonar>
        <div class="card stat"><div class="ico" style="background:var(--primary-soft);color:var(--primary)"><?= icono('caja', 22) ?></div><div class="val" style="font-size:22px"><?= bs($caja['monto_inicial']) ?></div><div class="lbl">Monto inicial</div></div>
        <div class="card stat"><div class="ico" style="background:var(--ok-soft);color:var(--ok)"><?= icono('recibo', 22) ?></div><div class="val" style="font-size:22px"><?= bs($caja['efectivo']) ?></div><div class="lbl">Ventas en efectivo</div></div>
        <div class="card stat"><div class="ico" style="background:var(--info-soft);color:var(--info)"><?= icono('etiqueta', 22) ?></div><div class="val" style="font-size:22px"><?= bs($caja['otros']) ?></div><div class="lbl">QR y transferencias</div></div>
        <div class="card stat"><div class="ico" style="background:var(--accent-soft);color:var(--accent)"><?= icono('check', 22) ?></div><div class="val" style="font-size:22px"><?= bs($esperado) ?></div><div class="lbl">Efectivo esperado</div></div>
    </div>
    <div class="split" data-escalonar>
        <div class="card">
            <div class="card-head"><h2>Ventas de esta caja</h2><span class="faint" style="font-size:13px"><?= (int) $caja['ventas'] ?> válida(s) · abierta <?= e(fecha_hora($caja['abierta_en'])) ?></span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Nota</th><th>Hora</th><th>Cliente</th><th>Pago</th><th style="text-align:right">Total</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <tr <?= $v['estado'] === 'ANULADA' ? 'style="opacity:.6"' : '' ?>>
                            <td class="mono"><a href="<?= e(url('/ventas/ver', ['id' => $v['id']])) ?>"><?= e($v['numero']) ?></a></td>
                            <td class="mono"><?= e(date('H:i', strtotime($v['fecha']))) ?></td>
                            <td><?= e($v['cliente']) ?></td><td><?= e($v['metodo']) ?></td>
                            <td style="text-align:right" class="mono"><?= bs($v['total']) ?></td><td><?= badge_estado_op($v['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$ventas): ?><div class="empty">Aún no hay ventas en esta caja.</div><?php endif; ?>
            </div>
        </div>
        <form method="post" action="/caja/cerrar" class="card card-pad stack" data-validar style="align-self:start"
              data-confirm="Cerrar la caja" data-confirm-texto="Después de cerrar no se pueden registrar más ventas hasta abrir otra caja." data-confirm-ok="Cerrar caja">
            <?= csrf_field() ?>
            <h2 style="font-size:17px"><?= icono('candado', 18) ?> Cerrar caja (arqueo)</h2>
            <label class="field"><span>Efectivo contado (Bs) *</span>
                <input class="input<?= clase_error('monto_declarado') ?>" type="number" name="monto_declarado" id="declarado" value="<?= e(old('monto_declarado')) ?>" required min="0" max="1000000" step="0.5" data-esperado="<?= $esperado ?>">
                <?= error_campo('monto_declarado') ?: '<small id="diferencia">Esperado: ' . bs($esperado) . '</small>' ?></label>
            <label class="field"><span>Observación</span>
                <textarea class="textarea" name="observacion" rows="2" maxlength="300" placeholder="Ej. se pagó un flete con caja chica"><?= e(old('observacion')) ?></textarea></label>
            <button class="btn btn-primary"><?= icono('candado') ?> Cerrar caja</button>
        </form>
    </div>
    <script>
    (function () {
        var i = document.getElementById('declarado'), s = document.getElementById('diferencia');
        if (!i || !s) return;
        i.addEventListener('input', function () {
            var d = (parseFloat(i.value) || 0) - parseFloat(i.dataset.esperado);
            var f = function (n) { return 'Bs ' + Math.abs(n).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
            s.textContent = i.value === '' ? 'Esperado: ' + f(parseFloat(i.dataset.esperado)) : (Math.abs(d) < 0.005 ? 'Cuadra exactamente.' : (d > 0 ? 'Sobran ' : 'Faltan ') + f(d));
            s.style.color = i.value === '' || Math.abs(d) < 0.005 ? '' : 'var(--warn)';
        });
    })();
    </script>
<?php endif; ?>

<div class="card mt anim">
    <div class="card-head"><h2>Historial de cajas</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Apertura</th><th>Cierre</th><th>Responsable</th><th style="text-align:right">Ventas</th><th style="text-align:right">Efectivo</th><th style="text-align:right">Otros</th><th style="text-align:right">Diferencia</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($cajas as $c): $dif = (float) $c['diferencia']; ?>
                <tr>
                    <td style="white-space:nowrap"><a href="<?= e(url('/ventas', ['desde' => substr($c['abierta_en'], 0, 10), 'hasta' => substr($c['abierta_en'], 0, 10)])) ?>"><?= e(fecha_hora($c['abierta_en'])) ?></a></td>
                    <td style="white-space:nowrap"><?= e($c['cerrada_en'] ? date('H:i', strtotime($c['cerrada_en'])) : '—') ?></td>
                    <td><?= e($c['usuario']) ?></td>
                    <td style="text-align:right"><?= (int) $c['ventas'] ?></td>
                    <td style="text-align:right" class="mono"><?= $c['total_efectivo'] !== null ? bs($c['total_efectivo']) : '—' ?></td>
                    <td style="text-align:right" class="mono"><?= $c['total_otros'] !== null ? bs($c['total_otros']) : '—' ?></td>
                    <td style="text-align:right;color:<?= $dif == 0.0 ? 'inherit' : 'var(--warn)' ?>" class="mono"><?= $c['diferencia'] !== null ? ($dif > 0 ? '+' : '') . bs($dif) : '—' ?></td>
                    <td><?= badge_estado_op($c['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
