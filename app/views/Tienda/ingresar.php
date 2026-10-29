<?php
/**
 * CU01 · Ingreso de clientes desde la tienda (correo y contraseña, o Google)
 * @var bool $google
 */
$titulo = 'Ingresar';
?>
<div class="auth-centro" style="padding:12px 0">
    <div class="card card-pad auth-tarjeta anim">
        <h1 style="font-size:28px">Ingresa para comprar</h1>
        <p class="soft" style="margin:8px 0 20px">Usa tu cuenta para pagar y recibir el comprobante en tu correo.</p>
        <?= View::parcial('boton_google', ['google' => $google]) ?>
        <form method="post" action="/login" class="stack" data-validar>
            <?= csrf_field() ?><input type="hidden" name="origen" value="tienda">
            <label class="field"><span>Correo electrónico</span>
                <span class="input-icon"><?= icono('correo') ?><input class="input<?= clase_error('correo') ?>" type="email" name="correo" value="<?= e(old('correo')) ?>" required maxlength="120" autocomplete="username" placeholder="nombre@gmail.com"></span>
                <?= error_campo('correo') ?></label>
            <label class="field"><span>Contraseña</span>
                <span class="input-icon"><?= icono('candado') ?><input class="input" type="password" name="password" id="clave-t" required maxlength="100" autocomplete="current-password" style="padding-right:48px">
                    <button type="button" class="btn btn-ghost btn-icon btn-sm trail" data-ver-clave="clave-t" aria-label="Mostrar contraseña" style="width:34px;height:34px"><?= icono('ver', 17) ?></button></span></label>
            <div style="text-align:right;margin-top:-6px"><a href="/recuperar" style="font-size:13.5px;font-weight:600">¿Olvidaste tu contraseña?</a></div>
            <button class="btn btn-primary btn-block" style="height:48px">Ingresar <?= icono('flecha', 17) ?></button>
        </form>
        <p class="faint" style="font-size:13.5px;margin:16px 0 0;text-align:center">¿Primera vez? <a href="/tienda/registro"><strong>Crea tu cuenta</strong></a></p>
    </div>
</div>
