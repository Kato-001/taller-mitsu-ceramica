<?php
/**
 * CU17 · Registrar / editar pieza (con foto)
 * @var ?array $pieza @var array $categorias
 */
$p = $pieza;
$titulo = $p ? 'Editar pieza' : 'Nueva pieza';
$foto = $p ? Pieza::foto($p) : null;
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/piezas"><?= icono('izquierda', 14) ?> Piezas</a></div>
        <h1><?= $p ? e($p['nombre']) : 'Registrar pieza' ?></h1>
        <p><?= $p ? 'Código ' . e($p['codigo']) . ' · el código no cambia. Un cambio de precio queda en el historial.' : 'El código se genera solo según la categoría (TZ, VS, VJ, DC). La pieza empieza con stock 0.' ?></p>
    </div>
</div>
<form method="post" action="/piezas/guardar" enctype="multipart/form-data" class="card card-pad anim" style="max-width:900px" data-validar>
    <?= csrf_field() ?>
    <?php if ($p): ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><?php endif; ?>
    <div class="grid-2" style="grid-template-columns:1fr 260px">
        <div class="stack">
            <label class="field"><span>Nombre *</span>
                <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre', $p['nombre'] ?? '')) ?>" required minlength="3" maxlength="100"><?= error_campo('nombre') ?></label>
            <div class="grid-3">
                <label class="field"><span>Categoría *</span>
                    <select class="select<?= clase_error('categoria_id') ?>" name="categoria_id" required>
                        <option value="">Seleccione…</option>
                        <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (string) old('categoria_id', $p['categoria_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
                    </select><?= error_campo('categoria_id') ?></label>
                <label class="field"><span>Precio (Bs) *</span>
                    <input class="input<?= clase_error('precio') ?>" type="number" name="precio" value="<?= e(old('precio', $p['precio'] ?? '')) ?>" required min="1" max="50000" step="0.5"><?= error_campo('precio') ?></label>
                <label class="field"><span>Stock mínimo *</span>
                    <input class="input<?= clase_error('stock_minimo') ?>" type="number" name="stock_minimo" value="<?= e(old('stock_minimo', $p['stock_minimo'] ?? '2')) ?>" required min="0" max="1000"><?= error_campo('stock_minimo') ?></label>
            </div>
            <label class="field"><span>Descripción</span>
                <textarea class="textarea" name="descripcion" rows="3" maxlength="500" placeholder="Técnica, esmalte, medidas…"><?= e(old('descripcion', $p['descripcion'] ?? '')) ?></textarea></label>
        </div>
        <label class="field">
            <span>Foto (JPG, PNG o WEBP · máx. 2 MB)</span>
            <div class="pieza-card"><div class="foto" id="vista-foto"><?= $foto ? '<img src="' . e($foto) . '" alt="Foto actual">' : icono('imagen', 42) ?></div></div>
            <input class="input<?= clase_error('foto') ?>" type="file" name="foto" accept="image/jpeg,image/png,image/webp" id="input-foto">
            <?= error_campo('foto') ?>
        </label>
    </div>
    <div class="row mt" style="justify-content:space-between">
        <span><?php if ($p): ?><button type="submit" form="estado-pieza" class="btn btn-ghost <?= $p['estado'] === 'ACTIVO' ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" data-sin-carga="1"><?= icono('power') ?> <?= $p['estado'] === 'ACTIVO' ? 'Retirar de la venta' : 'Poner a la venta' ?></button><?php endif; ?></span>
        <span class="row"><a href="/piezas" class="btn btn-ghost">Cancelar</a><button class="btn btn-primary"><?= icono('check') ?> Guardar</button></span>
    </div>
</form>
<?php if ($p): ?><form method="post" action="/piezas/estado" id="estado-pieza" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"></form><?php endif; ?>
<script>
document.getElementById('input-foto').addEventListener('change', function () {
    var f = this.files[0], caja = document.getElementById('vista-foto');
    if (!f) return;
    if (f.size > 2097152) { window.mitsuToast && mitsuToast('error', 'La foto no debe pasar de 2 MB.'); this.value = ''; return; }
    var img = document.createElement('img'); img.alt = 'Vista previa'; img.src = URL.createObjectURL(f);
    caja.innerHTML = ''; caja.appendChild(img);
});
</script>
