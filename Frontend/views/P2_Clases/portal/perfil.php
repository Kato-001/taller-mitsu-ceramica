<?php
/**
 * Portal del alumno · Mi perfil y contraseña
 * @var array $alumno
 */
$titulo = 'Mi perfil';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/portal"><?= icono('izquierda', 14) ?> Mi espacio</a></div>
        <h1>Mi perfil</h1>
        <p>Nivel: <?= e(NIVELES[$alumno['nivel']] ?? '') ?> (lo asigna tu maestra).</p>
    </div>
</div>
<div class="split" data-escalonar>
    <form method="post" action="/portal/perfil" class="card card-pad stack" data-validar>
        <?= csrf_field() ?>
        <h2 style="font-size:17px">Mis datos</h2>
        <?= View::parcial('campos_persona', ['p' => $alumno]) ?>
        <?= View::parcial('campos_alumno', ['a' => $alumno, 'conNivel' => false]) ?>
        <div class="row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icono('check') ?> Guardar</button></div>
    </form>
    <form method="post" action="/cuenta/password" class="card card-pad stack" data-validar>
        <?= csrf_field() ?>
        <h2 style="font-size:17px">Cambiar contraseña</h2>
        <label class="field"><span>Contraseña actual *</span>
            <input class="input<?= clase_error('actual') ?>" type="password" name="actual" required maxlength="100" autocomplete="current-password">
            <?= error_campo('actual') ?></label>
        <label class="field"><span>Nueva contraseña *</span>
            <input class="input<?= clase_error('password') ?>" type="password" name="password" required minlength="8" maxlength="100"
                   pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" data-msg="Mínimo 8 caracteres con letras y números." autocomplete="new-password">
            <?= error_campo('password') ?></label>
        <label class="field"><span>Confirmar contraseña *</span>
            <input class="input<?= clase_error('password_confirmacion') ?>" type="password" name="password_confirmacion" required maxlength="100" data-coincide="password" autocomplete="new-password">
            <?= error_campo('password_confirmacion') ?></label>
        <div class="row" style="justify-content:flex-end"><button class="btn btn-outline"><?= icono('llave') ?> Actualizar contraseña</button></div>
    </form>
</div>
