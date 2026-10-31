<?php
/**
 * CU13 · Materiales · CU16 · alertas de stock mínimo
 * @var array $materiales @var int $alertas @var array $categorias @var array $f
 */
$titulo = 'Materiales';
$valorTotal = array_sum(array_map(fn($m) => (float) $m['valor'], $materiales));
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('paquete', 14) ?> Inventario</div>
        <h1>Materiales e insumos</h1>
        <p>Arcillas, minerales y esmaltes. El stock solo cambia con compras, consumos, mermas o ajustes registrados.</p>
    </div>
    <div class="row">
        <a href="/compras/nueva" class="btn btn-outline"><?= icono('camion') ?> Registrar compra</a>
        <a href="/materiales/form" class="btn btn-primary"><?= icono('mas') ?> Nuevo material</a>
    </div>
</div>

<?php if ($alertas && ($f['alerta'] ?? '') !== '1'): ?>
    <div class="alert alert-warn anim"><?= icono('alerta') ?><div><strong><?= $alertas ?> material(es)</strong> están en el stock mínimo o por debajo. <a href="/materiales?alerta=1">Ver solo esos</a> · <a href="/compras/nueva">Registrar una compra</a></div></div>
<?php endif; ?>

<div class="card anim">
    <form class="toolbar" method="get">
        <div class="input-icon"><?= icono('buscar') ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar material" aria-label="Buscar"></div>
        <select class="select" name="categoria" data-autoenviar aria-label="Categoría">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (string) $f['categoria'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
        </select>
        <label class="check"><input type="checkbox" name="alerta" value="1" data-autoenviar <?= ($f['alerta'] ?? '') === '1' ? 'checked' : '' ?>><span>Solo bajo el mínimo</span></label>
        <span class="faint" style="margin-left:auto;font-size:13px">Valor del inventario: <strong><?= bs($valorTotal) ?></strong></span>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Material</th><th>Categoría</th><th style="text-align:right">Stock</th><th style="text-align:right">Mínimo</th><th style="text-align:right">Costo prom.</th><th>Situación</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($materiales as $m): ?>
                <tr <?= $m['estado'] !== 'ACTIVO' ? 'style="opacity:.55"' : '' ?>>
                    <td><a href="<?= e(url('/materiales/kardex', ['id' => $m['id']])) ?>"><strong><?= e($m['nombre']) ?></strong></a><?= $m['descripcion'] ? '<div class="faint" style="font-size:12.5px">' . e($m['descripcion']) . '</div>' : '' ?></td>
                    <td><?= e($m['categoria']) ?></td>
                    <td style="text-align:right" class="mono"><strong><?= cantidad($m['stock']) ?></strong> <?= e($m['unidad']) ?></td>
                    <td style="text-align:right" class="mono soft"><?= cantidad($m['stock_minimo']) ?></td>
                    <td style="text-align:right" class="mono"><?= bs($m['costo_promedio']) ?></td>
                    <td><?= $m['estado'] === 'ACTIVO' ? badge_stock((float) $m['stock'], (float) $m['stock_minimo']) : badge_estado($m['estado']) ?></td>
                    <td><div class="actions">
                        <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/materiales/kardex', ['id' => $m['id']])) ?>" title="Kardex y consumo"><?= icono('lista', 16) ?></a>
                        <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/compras/nueva', ['material' => $m['id']])) ?>" title="Comprar"><?= icono('camion', 16) ?></a>
                        <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/materiales/form', ['id' => $m['id']])) ?>" title="Editar"><?= icono('editar', 16) ?></a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$materiales): ?><div class="empty"><?= icono('paquete', 36) ?><div><strong>No hay materiales con esos filtros</strong></div></div><?php endif; ?>
    </div>
</div>
