<?php
/**
 * CU20 · Carrito de compras
 * @var array $lineas
 */
$titulo = 'Carrito';
$total = array_sum(array_column($lineas, 'subtotal'));
?>
<div class="page-head"><div><div class="eyebrow"><?= icono('carrito', 14) ?> Tu compra</div><h1>Carrito</h1></div></div>
<?php if (!$lineas): ?>
    <div class="card card-pad empty anim"><?= icono('carrito', 40) ?><div><strong>Tu carrito está vacío</strong></div><a href="/tienda" class="btn btn-primary" style="margin-top:12px">Ver piezas</a></div>
<?php else: ?>
<div class="t-dos">
    <form method="post" action="/tienda/carrito/actualizar" class="card card-pad anim">
        <?= csrf_field() ?>
        <?php foreach ($lineas as $l): $p = $l['pieza']; $foto = Pieza::foto($p); ?>
            <div class="t-linea">
                <?= $foto ? '<img src="' . e($foto) . '" alt="">' : '<span class="sin-foto">' . icono('vasija', 26) . '</span>' ?>
                <div>
                    <strong><?= e($p['nombre']) ?></strong>
                    <div class="faint" style="font-size:13px"><?= bs($p['precio']) ?> c/u<?= $l['pedida'] > $l['cantidad'] ? ' · <span style="color:var(--warn)">solo hay ' . (int) $p['stock'] . '</span>' : '' ?></div>
                    <div class="row mt" style="gap:8px;margin-top:8px">
                        <input class="input" type="number" name="cantidad[<?= $p['id'] ?>]" value="<?= (int) $l['cantidad'] ?>" min="0" max="<?= min(20, (int) $p['stock']) ?>" style="width:84px;height:36px" aria-label="Cantidad de <?= e($p['nombre']) ?>">
                        <button class="btn btn-ghost btn-sm btn-danger-ghost" name="quitar" value="<?= $p['id'] ?>" data-sin-carga="1"><?= icono('basura', 15) ?> Quitar</button>
                    </div>
                </div>
                <strong class="mono"><?= bs($l['subtotal']) ?></strong>
            </div>
        <?php endforeach; ?>
        <div class="row mt" style="justify-content:space-between">
            <a href="/tienda" class="btn btn-ghost"><?= icono('izquierda', 15) ?> Seguir comprando</a>
            <button class="btn btn-outline btn-sm" data-sin-carga="1"><?= icono('reiniciar', 15) ?> Actualizar cantidades</button>
        </div>
    </form>
    <div class="card card-pad stack anim" style="position:sticky;top:90px">
        <h2 style="font-size:18px">Resumen</h2>
        <div class="row-between"><span class="soft">Piezas</span><strong><?= array_sum(array_column($lineas, 'cantidad')) ?></strong></div>
        <div class="row-between"><span class="soft">Total</span><span class="total-grande"><?= bs($total) ?></span></div>
        <a href="/tienda/checkout" class="btn btn-primary btn-block" style="height:50px"><?= icono('candado') ?> Continuar con el pago</a>
        <p class="faint m0" style="font-size:12.5px">Para pagar te pediremos ingresar con tu correo o con Google, así te llega el comprobante.</p>
    </div>
</div>
<?php endif; ?>
