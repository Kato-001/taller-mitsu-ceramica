<?php
/**
 * CU18 · Ventas registradas
 * @var array $ventas @var array $totales @var array $f @var Paginador $pag
 */
$titulo = 'Ventas';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('recibo', 14) ?> Ventas</div>
        <h1>Ventas registradas</h1>
        <p><?= (int) $totales['ventas'] ?> venta(s) válidas por <strong><?= bs($totales['total']) ?></strong> en el período. Las ventas no se borran: se anulan con motivo.</p>
    </div>
    <?php if (Auth::puede('ventas.registrar')): ?><a href="/ventas/nueva" class="btn btn-primary"><?= icono('carrito') ?> Nueva venta</a><?php endif; ?>
</div>
<div class="card anim">
    <form class="toolbar" method="get">
        <div class="input-icon"><?= icono('buscar') ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="N.º de nota o cliente" aria-label="Buscar"></div>
        <select class="select" name="estado" data-autoenviar aria-label="Estado">
            <option value="">Válidas y anuladas</option>
            <option value="VALIDA" <?= $f['estado'] === 'VALIDA' ? 'selected' : '' ?>>Válidas</option>
            <option value="ANULADA" <?= $f['estado'] === 'ANULADA' ? 'selected' : '' ?>>Anuladas</option>
        </select>
        <input class="input" type="date" name="desde" value="<?= e($f['desde']) ?>" aria-label="Desde" style="max-width:160px">
        <input class="input" type="date" name="hasta" value="<?= e($f['hasta']) ?>" aria-label="Hasta" style="max-width:160px">
        <button class="btn btn-outline" data-sin-carga="1"><?= icono('filtro', 16) ?> Filtrar</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nota</th><th>Fecha</th><th>Cliente</th><th>Pago</th><th style="text-align:right">Unid.</th><th style="text-align:right">Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ventas as $v): ?>
                <tr <?= $v['estado'] === 'ANULADA' ? 'style="opacity:.6"' : '' ?>>
                    <td class="mono"><a href="<?= e(url('/ventas/ver', ['id' => $v['id']])) ?>"><?= e($v['numero']) ?></a></td>
                    <td style="white-space:nowrap"><?= e(fecha_hora($v['fecha'])) ?></td>
                    <td><?= e($v['cliente']) ?><div class="faint" style="font-size:12px"><?= e($v['vendedor']) ?></div></td>
                    <td><?= e($v['metodo']) ?></td>
                    <td style="text-align:right"><?= (int) $v['unidades'] ?></td>
                    <td style="text-align:right" class="mono"><strong><?= bs($v['total']) ?></strong></td>
                    <td><?= badge_estado_op($v['estado']) ?></td>
                    <td><a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/ventas/ver', ['id' => $v['id']])) ?>" title="Ver nota de venta"><?= icono('recibo', 16) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$ventas): ?><div class="empty"><?= icono('recibo', 36) ?><div><strong>No hay ventas en este período</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
