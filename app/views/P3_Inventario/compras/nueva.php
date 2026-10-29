<?php
/**
 * CU15 · Registrar compra (varios materiales en una sola operación)
 * @var array $proveedores @var array $materiales @var ?int $material
 */
$titulo = 'Registrar compra';
$scripts = ['compras.js'];
$items = $GLOBALS['_old']['items'] ?? [];
if (!$items) {
    $items = [['material_id' => $material ?: '', 'cantidad' => '', 'costo' => '']];
}
$opciones = function ($sel) use ($materiales) {
    $h = '<option value="">Seleccione material…</option>';
    foreach ($materiales as $m) {
        $h .= '<option value="' . $m['id'] . '" data-unidad="' . e($m['unidad']) . '" data-costo="' . e($m['costo_promedio']) . '"'
            . ((string) $sel === (string) $m['id'] ? ' selected' : '') . '>' . e($m['nombre'] . ' (' . $m['unidad'] . ') · stock ' . cantidad($m['stock'])) . '</option>';
    }
    return $h;
};
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/compras"><?= icono('izquierda', 14) ?> Compras</a></div>
        <h1>Registrar compra</h1>
        <p>Al guardar, el stock de cada material aumenta y se recalcula su costo promedio. Todo se guarda junto o nada.</p>
    </div>
</div>

<form method="post" action="/compras/guardar" class="card card-pad anim" data-validar id="form-compra">
    <?= csrf_field() ?>
    <div class="grid-3">
        <label class="field"><span>Proveedor *</span>
            <select class="select<?= clase_error('proveedor_id') ?>" name="proveedor_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($proveedores as $p): ?><option value="<?= $p['id'] ?>" <?= (string) old('proveedor_id') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre'] . ' · ' . $p['ciudad']) ?></option><?php endforeach; ?>
            </select><?= error_campo('proveedor_id') ?></label>
        <label class="field"><span>Fecha *</span>
            <input class="input<?= clase_error('fecha') ?>" type="date" name="fecha" value="<?= e(old('fecha', hoy())) ?>" required max="<?= hoy() ?>" min="<?= date('Y-m-d', strtotime('-60 days')) ?>"><?= error_campo('fecha') ?></label>
        <label class="field"><span>Observación</span>
            <input class="input" name="observacion" value="<?= e(old('observacion')) ?>" maxlength="300" placeholder="Ej. Factura 1234"></label>
    </div>

    <div class="section-title mt">Materiales comprados</div>
    <?= error_campo('items') ?>
    <div id="filas-compra" class="stack" style="gap:10px">
        <?php foreach ($items as $i => $it): ?>
            <div class="trat-row grid-3" style="grid-template-columns:2fr 1fr 1fr auto;align-items:end">
                <label class="field"><span>Material</span><select class="select" name="items[<?= $i ?>][material_id]" required data-material><?= $opciones($it['material_id'] ?? '') ?></select></label>
                <label class="field"><span>Cantidad</span><input class="input" type="number" name="items[<?= $i ?>][cantidad]" value="<?= e($it['cantidad'] ?? '') ?>" required min="0.01" max="100000" step="0.01" data-cantidad></label>
                <label class="field"><span>Costo unitario (Bs)</span><input class="input" type="number" name="items[<?= $i ?>][costo]" value="<?= e($it['costo'] ?? '') ?>" required min="0" max="100000" step="0.01" data-costo></label>
                <button type="button" class="btn btn-ghost btn-icon btn-danger-ghost" data-quitar-fila title="Quitar fila" aria-label="Quitar fila"><?= icono('basura', 16) ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <template id="plantilla-compra">
        <div class="trat-row grid-3" style="grid-template-columns:2fr 1fr 1fr auto;align-items:end">
            <label class="field"><span>Material</span><select class="select" name="items[__i__][material_id]" required data-material><?= $opciones('') ?></select></label>
            <label class="field"><span>Cantidad</span><input class="input" type="number" name="items[__i__][cantidad]" required min="0.01" max="100000" step="0.01" data-cantidad></label>
            <label class="field"><span>Costo unitario (Bs)</span><input class="input" type="number" name="items[__i__][costo]" required min="0" max="100000" step="0.01" data-costo></label>
            <button type="button" class="btn btn-ghost btn-icon btn-danger-ghost" data-quitar-fila title="Quitar fila" aria-label="Quitar fila"><?= icono('basura', 16) ?></button>
        </div>
    </template>
    <div class="row-between mt">
        <button type="button" class="btn btn-outline btn-sm" data-agregar="filas-compra" data-plantilla="plantilla-compra"><?= icono('mas', 15) ?> Agregar material</button>
        <div style="text-align:right"><span class="faint">Total de la compra</span><div class="total-grande" id="total-compra">Bs 0,00</div></div>
    </div>
    <div class="row mt" style="justify-content:flex-end">
        <a href="/compras" class="btn btn-ghost">Cancelar</a>
        <button class="btn btn-primary"><?= icono('check') ?> Registrar compra</button>
    </div>
</form>
