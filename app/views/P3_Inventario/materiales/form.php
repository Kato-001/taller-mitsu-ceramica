<?php
/**
 * CU13 · Registrar / editar material
 * @var ?array $material @var array $categorias @var array $unidades
 */
$m = $material;
$titulo = $m ? 'Editar material' : 'Nuevo material';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/materiales"><?= icono('izquierda', 14) ?> Materiales</a></div>
        <h1><?= $m ? e($m['nombre']) : 'Registrar material' ?></h1>
        <p><?= $m ? 'Stock actual: ' . cantidad($m['stock']) . ' ' . e($m['unidad']) . ' (cambia con compras y movimientos).' : 'El material empieza con stock 0; se carga con una compra.' ?></p>
    </div>
</div>
<form method="post" action="/materiales/guardar" class="card card-pad anim" style="max-width:760px" data-validar>
    <?= csrf_field() ?>
    <?php if ($m): ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><?php endif; ?>
    <div class="grid-2">
        <label class="field span-2"><span>Nombre *</span>
            <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre', $m['nombre'] ?? '')) ?>" required minlength="2" maxlength="80" placeholder="Ej. Arcilla roja">
            <?= error_campo('nombre') ?: '<small>No puede repetirse (sin importar mayúsculas ni tildes).</small>' ?></label>
        <label class="field"><span>Categoría *</span>
            <select class="select<?= clase_error('categoria_id') ?>" name="categoria_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (string) old('categoria_id', $m['categoria_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
            </select><?= error_campo('categoria_id') ?></label>
        <label class="field"><span>Unidad de medida *</span>
            <select class="select<?= clase_error('unidad_id') ?>" name="unidad_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($unidades as $u): ?><option value="<?= $u['id'] ?>" <?= (string) old('unidad_id', $m['unidad_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['nombre'] . ' (' . $u['abreviatura'] . ')') ?></option><?php endforeach; ?>
            </select><?= error_campo('unidad_id') ?></label>
        <label class="field"><span>Stock mínimo *</span>
            <input class="input<?= clase_error('stock_minimo') ?>" type="number" name="stock_minimo" value="<?= e(old('stock_minimo', $m['stock_minimo'] ?? '')) ?>" required min="0" max="100000" step="0.01">
            <?= error_campo('stock_minimo') ?: '<small>Al llegar a este nivel el sistema muestra la alerta.</small>' ?></label>
        <label class="field span-2"><span>Descripción</span>
            <textarea class="textarea" name="descripcion" rows="2" maxlength="300"><?= e(old('descripcion', $m['descripcion'] ?? '')) ?></textarea></label>
    </div>
    <div class="row mt" style="justify-content:space-between">
        <span>
            <?php if ($m): ?>
                <button type="submit" form="estado-material" class="btn btn-ghost <?= $m['estado'] === 'ACTIVO' ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" data-sin-carga="1"><?= icono('power') ?> <?= $m['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button>
            <?php endif; ?>
        </span>
        <span class="row"><a href="/materiales" class="btn btn-ghost">Cancelar</a><button class="btn btn-primary"><?= icono('check') ?> Guardar</button></span>
    </div>
</form>
<?php if ($m): ?>
    <form method="post" action="/materiales/estado" id="estado-material" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"></form>
<?php endif; ?>
