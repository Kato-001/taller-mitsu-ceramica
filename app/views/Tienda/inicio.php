<?php
/**
 * Portada pública: lo primero que ve el visitante (piezas a la venta y clases).
 * @var array $piezas @var array $talleres
 */
$titulo = 'Inicio';
$u = Auth::usuario();
?>
<section class="t-hero">
    <div class="anim">
        <div class="eyebrow"><?= icono('vasija', 14) ?> Cerámica artesanal boliviana</div>
        <h1>Piezas hechas a mano, <em>con barro y fuego.</em></h1>
        <p>Tazas, vasijas y vajilla creadas en nuestro taller con arcillas y minerales de Cochabamba y La Paz. Compra en línea y paga con QR, tarjeta o PayPal.</p>
        <div class="row">
            <a href="/tienda" class="btn btn-primary" style="height:48px;padding:0 22px"><?= icono('tienda') ?> Ver la tienda</a>
            <a href="#talleres" class="btn btn-outline" style="height:48px;padding:0 22px"><?= icono('paleta') ?> Clases de cerámica</a>
        </div>
        <div class="t-sellos mt">
            <span class="t-sello"><?= icono('ok-circulo', 15) ?> Hecho a mano</span>
            <span class="t-sello"><?= icono('caja', 15) ?> Pago seguro</span>
            <span class="t-sello"><?= icono('correo', 15) ?> Comprobante a tu correo</span>
        </div>
    </div>
    <div class="t-collage anim" style="--i:2">
        <img src="/assets/img/piezas/jarra-andina.jpg" alt="Jarra con rostro y friso de felinos">
        <img src="/assets/img/piezas/set-cafe.jpg" alt="Vaso esmaltado con bandeja y alfajor">
        <img src="/assets/img/piezas/taza-celeste.jpg" alt="Taza esmaltada celeste">
    </div>
</section>

<div class="t-seccion">
    <div><div class="eyebrow">Tienda</div><h2>Piezas disponibles</h2></div>
    <a href="/tienda" class="btn btn-ghost">Ver todas <?= icono('flecha', 16) ?></a>
</div>
<?= View::parcial('tarjetas_tienda', ['piezas' => $piezas]) ?>

<div class="t-seccion" id="talleres">
    <div><div class="eyebrow">Aprende con nosotras</div><h2>Clases de cerámica</h2></div>
    <a href="<?= $u && Auth::puede('portal.alumno') ? '/portal/reservar' : '/registro' ?>" class="btn btn-primary"><?= icono('calendario-mas') ?> <?= $u && Auth::puede('portal.alumno') ? 'Reservar clase' : 'Crear cuenta de alumno' ?></a>
</div>
<div class="clases-lista" data-escalonar>
    <?php foreach ($talleres as $t): ?>
        <div class="clase-card">
            <span class="fecha-chip"><?= e(PUBLICOS[$t['publico']]) ?> · <?= e(NIVELES[$t['nivel']]) ?></span>
            <strong style="font-size:18px;font-family:var(--serif)"><?= e($t['nombre']) ?></strong>
            <p class="soft m0" style="font-size:14px"><?= e($t['descripcion']) ?></p>
            <span style="font-size:14px"><strong><?= bs($t['precio_clase']) ?></strong> por clase · <strong><?= bs($t['precio_mensual']) ?></strong> al mes</span>
        </div>
    <?php endforeach; ?>
</div>
<p class="faint" style="font-size:12.5px;margin-top:12px">Precios de referencia (datos de demostración).</p>
