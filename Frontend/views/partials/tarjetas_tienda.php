<?php
/**
 * Tarjetas de piezas de la tienda con botón "Agregar al carrito".
 * @var array $piezas
 */
?>
<div class="piezas-grid" data-escalonar>
    <?php foreach ($piezas as $p): $foto = Pieza::foto($p); ?>
        <div class="pieza-card">
            <a href="<?= e(url('/tienda/pieza', ['id' => $p['id']])) ?>" class="foto"><?= $foto ? '<img src="' . e($foto) . '" alt="' . e($p['nombre']) . '" loading="lazy">' : icono('vasija', 42) ?></a>
            <div class="cuerpo">
                <span class="faint" style="font-size:12px"><?= e($p['categoria']) ?></span>
                <a href="<?= e(url('/tienda/pieza', ['id' => $p['id']])) ?>" style="color:inherit"><strong><?= e($p['nombre']) ?></strong></a>
                <div class="pie">
                    <span class="precio"><?= bs($p['precio']) ?></span>
                    <span class="faint" style="font-size:12px"><?= (int) $p['stock'] <= 2 ? '¡Quedan ' . (int) $p['stock'] . '!' : 'Disponible' ?></span>
                </div>
                <form method="post" action="/tienda/carrito/agregar">
                    <?= csrf_field() ?><input type="hidden" name="pieza_id" value="<?= $p['id'] ?>">
                    <button class="btn btn-primary btn-sm btn-block"><?= icono('carrito', 15) ?> Agregar al carrito</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$piezas): ?><div class="card card-pad empty"><?= icono('vasija', 36) ?><div><strong>No hay piezas disponibles por ahora</strong></div></div><?php endif; ?>
