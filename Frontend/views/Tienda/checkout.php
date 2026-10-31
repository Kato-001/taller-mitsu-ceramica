<?php
/**
 * CU20 · Confirmar datos de entrega y crear el pedido
 * @var array $lineas @var array $u
 */
$titulo = 'Confirmar pedido';
$total = array_sum(array_column($lineas, 'subtotal'));
$entrega = old('entrega', 'RETIRO');
?>
<div class="page-head"><div><div class="eyebrow"><a href="/tienda/carrito"><?= icono('izquierda', 14) ?> Carrito</a></div><h1>Confirmar pedido</h1>
    <p>Comprando como <strong><?= e($u['nombre_completo']) ?></strong> · el comprobante llegará a <strong><?= e($u['correo']) ?></strong>.</p></div></div>
<form method="post" action="/tienda/checkout" class="t-dos" data-validar>
    <?= csrf_field() ?>
    <div class="card card-pad stack anim">
        <h2 style="font-size:18px">¿Cómo recibes tu pedido?</h2>
        <label class="t-opcion"><input type="radio" name="entrega" value="RETIRO" <?= $entrega === 'RETIRO' ? 'checked' : '' ?>>
            <span><strong>Retiro en el taller</strong><br><span class="soft" style="font-size:13.5px">Sin costo. Te avisamos cuando esté listo.</span></span></label>
        <label class="t-opcion"><input type="radio" name="entrega" value="ENVIO" <?= $entrega === 'ENVIO' ? 'checked' : '' ?>>
            <span><strong>Envío a domicilio (Santa Cruz)</strong><br><span class="soft" style="font-size:13.5px">El costo del envío se coordina por teléfono.</span></span></label>
        <label class="field"><span>Dirección de envío</span>
            <input class="input<?= clase_error('direccion') ?>" name="direccion" value="<?= e(old('direccion')) ?>" maxlength="200" placeholder="Barrio, calle, número y referencia">
            <?= error_campo('direccion') ?></label>
        <label class="field"><span>Teléfono de contacto *</span>
            <input class="input<?= clase_error('telefono') ?>" type="tel" name="telefono" value="<?= e(old('telefono', $u['telefono'] ?? '')) ?>" required maxlength="20" pattern="[0-9+\- ]{7,20}" data-msg="De 7 a 20 dígitos.">
            <?= error_campo('telefono') ?></label>
        <label class="field"><span>Nota para el taller</span>
            <textarea class="textarea" name="nota" rows="2" maxlength="300" placeholder="Ej. es para regalo"><?= e(old('nota')) ?></textarea></label>
    </div>
    <div class="card card-pad stack anim">
        <h2 style="font-size:18px">Tu pedido</h2>
        <?php foreach ($lineas as $l): ?>
            <div class="row-between" style="font-size:14px"><span><?= (int) $l['cantidad'] ?> × <?= e($l['pieza']['nombre']) ?></span><strong class="mono"><?= bs($l['subtotal']) ?></strong></div>
        <?php endforeach; ?>
        <div class="row-between" style="border-top:1px solid var(--border);padding-top:10px"><span class="soft">Total</span><span class="total-grande"><?= bs($total) ?></span></div>
        <button class="btn btn-primary btn-block" style="height:50px"><?= icono('candado') ?> Ir a pagar</button>
        <p class="faint m0" style="font-size:12.5px">Al confirmar, las piezas quedan reservadas por 30 minutos mientras completas el pago.</p>
    </div>
</form>
