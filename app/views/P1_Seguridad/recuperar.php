<?php
/** CU02 · Recuperar contraseña · Paso 1: correo */
$titulo = 'Recuperar contraseña';
?>
<div class="auth-centro">
    <div class="card card-pad auth-tarjeta anim">
        <a href="/login" class="brand" style="padding:0 0 22px">
            <span class="brand-logo"><?= icono('vasija', 22) ?></span>
            <span><strong>Mitsü</strong><small><?= e(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü')) ?></small></span>
        </a>
        <div class="pasos-rec"><span class="on">1</span><i></i><span>2</span></div>
        <h1 style="font-size:26px">¿Olvidaste tu contraseña?</h1>
        <p class="soft" style="margin:8px 0 22px">Escribe el correo de tu cuenta. Te enviaremos un código de 6 dígitos para crear una contraseña nueva.</p>
        <form method="post" action="/recuperar" class="stack" data-validar>
            <?= csrf_field() ?>
            <label class="field"><span>Correo electrónico</span>
                <span class="input-icon"><?= icono('correo') ?>
                    <input class="input<?= clase_error('correo') ?>" type="email" name="correo" value="<?= e(old('correo')) ?>" required maxlength="120" autocomplete="email" autofocus placeholder="nombre@correo.com">
                </span>
                <?= error_campo('correo') ?></label>
            <button class="btn btn-primary btn-block" style="height:48px"><?= icono('correo', 17) ?> Enviar código</button>
        </form>
        <p class="faint" style="font-size:13px;margin:16px 0 0;text-align:center"><a href="/login">Volver al inicio de sesión</a></p>
    </div>
</div>
