<?php
/**
 * CU20 · Ficha de una pieza
 * @var array $pieza @var array $otras
 */
$p = $pieza;
$titulo = $p['nombre'];
$foto = Pieza::foto($p);
?>
<p style="margin-bottom:14px"><a href="/tienda" class="faint"><?= icono('izquierda', 14) ?> Volver a la tienda</a></p>
<div class="t-ficha anim">
    <div class="foto-grande"><?= $foto ? '<img src="' . e($foto) . '" alt="' . e($p['nombre']) . '">' : icono('vasija', 72) ?></div>
    <div class="stack">
        <span class="eyebrow"><?= e($p['categoria']) ?> · <span class="mono"><?= e($p['codigo']) ?></span></span>
        <h1><?= e($p['nombre']) ?></h1>
        <span class="t-precio"><?= bs($p['precio']) ?></span>
        <p class="soft" style="font-size:16px"><?= e($p['descripcion'] ?: 'Pieza artesanal del Taller Mitsü.') ?></p>
        <p class="m0"><?= (int) $p['stock'] > 0 ? badge('Disponible: ' . (int) $p['stock'] . ' unidad(es)', 'badge-ok') : badge('Agotada', 'badge-danger') ?></p>
        <?php if ((int) $p['stock'] > 0): ?>
            <form method="post" action="/tienda/carrito/agregar" class="row" style="align-items:end">
                <?= csrf_field() ?><input type="hidden" name="pieza_id" value="<?= $p['id'] ?>"><input type="hidden" name="volver" value="carrito">
                <label class="field" style="width:110px"><span>Cantidad</span>
                    <input class="input" type="number" name="cantidad" value="1" min="1" max="<?= min(20, (int) $p['stock']) ?>" required></label>
                <button class="btn btn-primary" style="height:46px;flex:1"><?= icono('carrito') ?> Agregar al carrito</button>
            </form>
        <?php endif; ?>
        <div class="kv-list mt">
            <div class="kv"><span>Pago</span><strong>QR, tarjeta o PayPal</strong></div>
            <div class="kv"><span>Entrega</span><strong>Retiro en el taller o envío en la ciudad</strong></div>
            <div class="kv"><span>Comprobante</span><strong>Llega a tu correo</strong></div>
        </div>
    </div>
</div>
<?php if ($otras): ?>
    <div class="t-seccion"><h2>También te puede gustar</h2></div>
    <?= View::parcial('tarjetas_tienda', ['piezas' => $otras]) ?>
<?php endif; ?>
