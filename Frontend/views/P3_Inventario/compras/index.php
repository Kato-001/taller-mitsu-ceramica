<?php
/**
 * CU15 · Compras a proveedores
 * @var array $compras @var array $proveedores @var float $totalMes @var array $f @var Paginador $pag
 */
$titulo = 'Compras';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('camion', 14) ?> Inventario</div>
        <h1>Compras de materiales</h1>
        <p>Este mes se compró <strong><?= bs($totalMes) ?></strong>. Una compra no se borra: se anula con motivo.</p>
    </div>
    <a href="/compras/nueva" class="btn btn-primary"><?= icono('mas') ?> Registrar compra</a>
</div>
<div class="card anim">
    <form class="toolbar" method="get">
        <select class="select" name="proveedor" data-autoenviar aria-label="Proveedor">
            <option value="">Todos los proveedores</option>
            <?php foreach ($proveedores as $p): ?><option value="<?= $p['id'] ?>" <?= (string) $f['proveedor'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="estado" data-autoenviar aria-label="Estado">
            <option value="">Registradas y anuladas</option>
            <option value="REGISTRADA" <?= $f['estado'] === 'REGISTRADA' ? 'selected' : '' ?>>Registradas</option>
            <option value="ANULADA" <?= $f['estado'] === 'ANULADA' ? 'selected' : '' ?>>Anuladas</option>
        </select>
        <input class="input" type="date" name="desde" value="<?= e($f['desde']) ?>" aria-label="Desde" style="max-width:160px">
        <input class="input" type="date" name="hasta" value="<?= e($f['hasta']) ?>" aria-label="Hasta" style="max-width:160px">
        <button class="btn btn-outline" data-sin-carga="1"><?= icono('filtro', 16) ?> Filtrar</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>N.º</th><th>Fecha</th><th>Proveedor</th><th>Ítems</th><th style="text-align:right">Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($compras as $c): ?>
                <tr>
                    <td class="mono"><a href="<?= e(url('/compras/ver', ['id' => $c['id']])) ?>"><?= e($c['numero']) ?></a></td>
                    <td><?= e(fecha($c['fecha'])) ?></td>
                    <td><?= e($c['proveedor']) ?><div class="faint" style="font-size:12px"><?= e($c['usuario']) ?></div></td>
                    <td><?= (int) $c['items'] ?></td>
                    <td style="text-align:right" class="mono"><strong><?= bs($c['total']) ?></strong></td>
                    <td><?= badge_estado_op($c['estado']) ?></td>
                    <td><a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/compras/ver', ['id' => $c['id']])) ?>" title="Ver detalle"><?= icono('ver', 16) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$compras): ?><div class="empty"><?= icono('camion', 36) ?><div><strong>No hay compras con esos filtros</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
