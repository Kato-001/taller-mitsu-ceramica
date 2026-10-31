<?php
/**
 * CU17 · Stock de una pieza: producción, ajustes, kardex e historial de precios
 * @var array $pieza @var array $movimientos @var array $precios
 */
$p = $pieza;
$titulo = $p['nombre'];
$foto = Pieza::foto($p);
$tipos = ['PRODUCCION' => ['Producción', 'badge-ok'], 'VENTA' => ['Venta', 'badge-info'], 'ANULACION' => ['Venta anulada', 'badge-accent'],
          'AJUSTE_MAS' => ['Ajuste +', 'badge-accent'], 'AJUSTE_MENOS' => ['Ajuste −', 'badge-warn'],
          'VENTA_WEB' => ['Venta en línea', 'badge-info'], 'DEVOLUCION_WEB' => ['Pedido web cancelado', 'badge-accent']];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/piezas"><?= icono('izquierda', 14) ?> Piezas</a></div>
        <h1><?= e($p['nombre']) ?></h1>
        <p class="mono"><?= e($p['codigo']) ?> · <?= e($p['categoria']) ?> · <?= bs($p['precio']) ?> · <?= badge_stock((float) $p['stock'], (float) $p['stock_minimo']) ?></p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/piezas/form', ['id' => $p['id']])) ?>"><?= icono('editar') ?> Editar pieza</a>
</div>

<div class="split" data-escalonar>
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Kardex</h2><span class="faint" style="font-size:13px">Stock actual: <strong><?= (int) $p['stock'] ?></strong> · mínimo <?= (int) $p['stock_minimo'] ?></span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th style="text-align:right">Cant.</th><th style="text-align:right">Saldo</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach ($movimientos as $mv): $entra = in_array($mv['tipo'], ['PRODUCCION', 'ANULACION', 'AJUSTE_MAS', 'DEVOLUCION_WEB'], true); ?>
                        <tr>
                            <td class="mono" style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($mv['fecha']))) ?></td>
                            <td><?= badge($tipos[$mv['tipo']][0], $tipos[$mv['tipo']][1], false) ?></td>
                            <td style="text-align:right;color:<?= $entra ? 'var(--ok)' : 'var(--danger)' ?>" class="mono"><?= $entra ? '+' : '−' ?><?= (int) $mv['cantidad'] ?></td>
                            <td style="text-align:right" class="mono"><strong><?= (int) $mv['stock_resultante'] ?></strong></td>
                            <td><?= $mv['venta_id'] ? '<a href="' . e(url('/ventas/ver', ['id' => $mv['venta_id']])) . '">' . e($mv['motivo']) . '</a>' : ($mv['pedido_id'] ? '<a href="' . e(url('/pedidos/ver', ['id' => $mv['pedido_id']])) . '">' . e($mv['motivo']) . '</a>' : e($mv['motivo'])) ?><div class="faint" style="font-size:12px"><?= e($mv['usuario']) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$movimientos): ?><div class="empty">Sin movimientos: registre la producción para tener stock.</div><?php endif; ?>
            </div>
        </div>
        <?php if ($precios): ?>
        <div class="card">
            <div class="card-head"><h2>Historial de precios</h2></div>
            <div class="table-wrap"><table class="table"><tbody>
                <?php foreach ($precios as $h): ?>
                    <tr><td><?= e(fecha_hora($h['fecha'])) ?></td><td class="mono"><?= bs($h['precio_anterior']) ?> → <strong><?= bs($h['precio_nuevo']) ?></strong></td><td class="faint"><?= e($h['usuario'] ?? '—') ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <div class="pieza-card"><div class="foto"><?= $foto ? '<img src="' . e($foto) . '" alt="' . e($p['nombre']) . '">' : icono('vasija', 48) ?></div>
            <div class="cuerpo"><span class="soft" style="font-size:13.5px"><?= e($p['descripcion'] ?: 'Sin descripción') ?></span></div></div>
        <?php if ($p['estado'] === 'ACTIVO'): ?>
        <form method="post" action="/piezas/movimiento" class="card card-pad stack" data-validar>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <h2 style="font-size:17px"><?= icono('paquete', 18) ?> Registrar producción o ajuste</h2>
            <label class="field"><span>Tipo *</span>
                <select class="select" name="tipo" required>
                    <?php foreach (Pieza::TIPOS_MOVIMIENTO as $k => $v): ?><option value="<?= $k ?>" <?= old('tipo') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select></label>
            <label class="field"><span>Cantidad (unidades) *</span>
                <input class="input<?= clase_error('cantidad') ?>" type="number" name="cantidad" value="<?= e(old('cantidad')) ?>" required min="1" max="1000"><?= error_campo('cantidad') ?></label>
            <label class="field"><span>Motivo *</span>
                <input class="input<?= clase_error('motivo') ?>" name="motivo" value="<?= e(old('motivo')) ?>" required minlength="5" maxlength="255" placeholder="Ej. Horneada del viernes"><?= error_campo('motivo') ?></label>
            <button class="btn btn-primary"><?= icono('check') ?> Registrar</button>
        </form>
        <?php endif; ?>
    </div>
</div>
