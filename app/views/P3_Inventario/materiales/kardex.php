<?php
/**
 * CU16 · Kardex del material y registro de consumo, merma o ajuste
 * @var array $material @var array $movimientos
 */
$m = $material;
$titulo = $m['nombre'];
$tipos = ['ENTRADA' => ['Entrada (compra)', 'badge-ok'], 'CONSUMO' => ['Consumo', 'badge-info'], 'MERMA' => ['Merma', 'badge-danger'],
          'AJUSTE_MAS' => ['Ajuste +', 'badge-accent'], 'AJUSTE_MENOS' => ['Ajuste −', 'badge-warn']];
$bajo = (float) $m['stock'] <= (float) $m['stock_minimo'];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/materiales"><?= icono('izquierda', 14) ?> Materiales</a></div>
        <h1><?= e($m['nombre']) ?></h1>
        <p><?= e($m['categoria']) ?> · <?= badge_stock((float) $m['stock'], (float) $m['stock_minimo']) ?> <?= $m['estado'] !== 'ACTIVO' ? badge_estado($m['estado']) : '' ?></p>
    </div>
    <div class="row">
        <a class="btn btn-outline" href="<?= e(url('/materiales/form', ['id' => $m['id']])) ?>"><?= icono('editar') ?> Editar</a>
        <a class="btn btn-primary" href="<?= e(url('/compras/nueva', ['material' => $m['id']])) ?>"><?= icono('camion') ?> Comprar</a>
    </div>
</div>

<div class="stats" data-escalonar>
    <div class="card stat"><div class="ico" style="background:<?= $bajo ? 'var(--warn-soft)' : 'var(--ok-soft)' ?>;color:<?= $bajo ? 'var(--warn)' : 'var(--ok)' ?>"><?= icono('paquete', 22) ?></div><div class="val"><?= cantidad($m['stock']) ?> <small style="font-size:14px"><?= e($m['unidad']) ?></small></div><div class="lbl">Stock actual</div></div>
    <div class="card stat"><div class="ico" style="background:var(--warn-soft);color:var(--warn)"><?= icono('alerta', 22) ?></div><div class="val"><?= cantidad($m['stock_minimo']) ?></div><div class="lbl">Stock mínimo</div></div>
    <div class="card stat"><div class="ico" style="background:var(--primary-soft);color:var(--primary)"><?= icono('etiqueta', 22) ?></div><div class="val" style="font-size:22px"><?= bs($m['costo_promedio']) ?></div><div class="lbl">Costo promedio por <?= e($m['unidad']) ?></div></div>
    <div class="card stat"><div class="ico" style="background:var(--accent-soft);color:var(--accent)"><?= icono('caja', 22) ?></div><div class="val" style="font-size:22px"><?= bs($m['valor']) ?></div><div class="lbl">Valor en inventario</div></div>
</div>

<div class="split" data-escalonar>
    <div class="card">
        <div class="card-head"><h2>Kardex (movimientos)</h2><span class="faint" style="font-size:13px">Últimos <?= count($movimientos) ?></span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th style="text-align:right">Cantidad</th><th style="text-align:right">Saldo</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php foreach ($movimientos as $mv): $entra = in_array($mv['tipo'], ['ENTRADA', 'AJUSTE_MAS'], true); ?>
                    <tr>
                        <td class="mono" style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($mv['fecha']))) ?></td>
                        <td><?= badge($tipos[$mv['tipo']][0] ?? $mv['tipo'], $tipos[$mv['tipo']][1] ?? 'badge-off', false) ?></td>
                        <td style="text-align:right;color:<?= $entra ? 'var(--ok)' : 'var(--danger)' ?>" class="mono"><?= $entra ? '+' : '−' ?><?= cantidad($mv['cantidad']) ?></td>
                        <td style="text-align:right" class="mono"><strong><?= cantidad($mv['stock_resultante']) ?></strong></td>
                        <td><?= $mv['compra_id'] ? '<a href="' . e(url('/compras/ver', ['id' => $mv['compra_id']])) . '">' . e($mv['motivo']) . '</a>' : e($mv['motivo']) ?><div class="faint" style="font-size:12px"><?= e($mv['usuario']) ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$movimientos): ?><div class="empty">Sin movimientos todavía.</div><?php endif; ?>
        </div>
    </div>

    <?php if ($m['estado'] === 'ACTIVO'): ?>
    <form method="post" action="/materiales/movimiento" class="card card-pad stack" data-validar style="align-self:start">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
        <h2 style="font-size:17px"><?= icono('menos', 18) ?> Registrar consumo, merma o ajuste</h2>
        <label class="field"><span>Tipo *</span>
            <select class="select<?= clase_error('tipo') ?>" name="tipo" required>
                <?php foreach (Material::TIPOS_SALIDA as $k => $v): ?><option value="<?= $k ?>" <?= old('tipo') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select><?= error_campo('tipo') ?></label>
        <label class="field"><span>Cantidad (<?= e($m['unidad']) ?>) *</span>
            <input class="input<?= clase_error('cantidad') ?>" type="number" name="cantidad" value="<?= e(old('cantidad')) ?>" required min="0.01" max="100000" step="0.01">
            <?= error_campo('cantidad') ?: '<small>Disponible: ' . cantidad($m['stock']) . ' ' . e($m['unidad']) . '. El sistema no permite dejar stock negativo.</small>' ?></label>
        <label class="field"><span>Motivo *</span>
            <input class="input<?= clase_error('motivo') ?>" name="motivo" value="<?= e(old('motivo')) ?>" required minlength="5" maxlength="255" placeholder="Ej. Clase de torno del martes">
            <?= error_campo('motivo') ?></label>
        <button class="btn btn-primary"><?= icono('check') ?> Registrar movimiento</button>
    </form>
    <?php endif; ?>
</div>
