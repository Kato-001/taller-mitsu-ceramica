<?php
/**
 * CU17 · Catálogo de piezas de cerámica
 * @var array $piezas @var array $categorias @var array $f
 */
$titulo = 'Piezas de cerámica';
$gestiona = Auth::puede('piezas.gestionar');
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('vasija', 14) ?> Ventas</div>
        <h1>Piezas de cerámica</h1>
        <p>Piezas hechas en el taller. Fotos reales del taller; precios y stocks de demostración.</p>
    </div>
    <?php if ($gestiona): ?><a href="/piezas/form" class="btn btn-primary"><?= icono('mas') ?> Nueva pieza</a><?php endif; ?>
</div>

<form class="card toolbar anim" method="get" style="margin-bottom:16px">
    <div class="input-icon"><?= icono('buscar') ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nombre o código" aria-label="Buscar"></div>
    <select class="select" name="categoria" data-autoenviar aria-label="Categoría">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (string) $f['categoria'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
    </select>
    <label class="check"><input type="checkbox" name="alerta" value="1" data-autoenviar <?= ($f['alerta'] ?? '') === '1' ? 'checked' : '' ?>><span>Solo stock bajo</span></label>
    <span class="faint" style="margin-left:auto;font-size:13px"><?= count($piezas) ?> pieza(s)</span>
</form>

<div class="piezas-grid" data-escalonar>
    <?php foreach ($piezas as $p): $foto = Pieza::foto($p); ?>
        <div class="pieza-card<?= $p['estado'] !== 'ACTIVO' ? ' inactiva' : '' ?>">
            <div class="foto"><?= $foto ? '<img src="' . e($foto) . '" alt="' . e($p['nombre']) . '" loading="lazy">' : icono('vasija', 42) ?></div>
            <div class="cuerpo">
                <span class="faint mono" style="font-size:12px"><?= e($p['codigo']) ?> · <?= e($p['categoria']) ?></span>
                <strong><?= e($p['nombre']) ?></strong>
                <div class="pie">
                    <span class="precio"><?= bs($p['precio']) ?></span>
                    <?= $p['estado'] === 'ACTIVO' ? badge_stock((float) $p['stock'], (float) $p['stock_minimo']) : badge('Retirada', 'badge-off') ?>
                </div>
                <div class="row-between" style="font-size:12.5px"><span class="soft">Stock: <strong><?= (int) $p['stock'] ?></strong></span><span class="faint"><?= (int) $p['vendidas'] ?> vendida(s)</span></div>
                <?php if ($gestiona): ?>
                    <div class="row" style="gap:6px">
                        <a class="btn btn-outline btn-sm" style="flex:1" href="<?= e(url('/piezas/kardex', ['id' => $p['id']])) ?>"><?= icono('lista', 14) ?> Stock</a>
                        <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/piezas/form', ['id' => $p['id']])) ?>" title="Editar"><?= icono('editar', 15) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$piezas): ?><div class="card empty"><?= icono('vasija', 36) ?><div><strong>No hay piezas con esos filtros</strong></div></div><?php endif; ?>
