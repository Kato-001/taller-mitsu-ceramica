<?php
/**
 * Mi cuenta: datos de la sesión y cambio de contraseña.
 * @var array $u
 */
$titulo = 'Mi cuenta';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('cuenta', 14) ?> Cuenta</div>
        <h1>Mi cuenta</h1>
        <p>Sus datos de acceso al sistema.</p>
    </div>
</div>
<div class="split" data-escalonar>
    <div class="card card-pad">
        <div class="user-cell" style="margin-bottom:18px">
            <div class="avatar" style="width:56px;height:56px;font-size:20px"><?= e(iniciales($u['nombre_completo'])) ?></div>
            <div><strong style="font-size:18px"><?= e($u['nombre_completo']) ?></strong><small><?= e($u['correo']) ?></small></div>
        </div>
        <div class="kv-list">
            <div class="kv"><span>Rol</span><strong><?= badge_rol($u['rol']) ?></strong></div>
            <div class="kv"><span>Teléfono</span><strong><?= e($u['telefono'] ?: '—') ?></strong></div>
            <div class="kv"><span>Último acceso</span><strong><?= e(fecha_hora($u['ultimo_acceso'])) ?></strong></div>
            <div class="kv"><span>Permisos</span><strong><?= count($u['permisos']) ?></strong></div>
        </div>
    </div>
    <form method="post" action="/cuenta/password" class="card card-pad stack" data-validar>
        <?= csrf_field() ?>
        <h2 style="font-size:17px">Cambiar contraseña</h2>
        <label class="field"><span>Contraseña actual *</span>
            <input class="input<?= clase_error('actual') ?>" type="password" name="actual" required maxlength="100" autocomplete="current-password">
            <?= error_campo('actual') ?></label>
        <?= View::parcial('campos_clave', ['editando' => false]) ?>
        <div class="row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icono('llave') ?> Actualizar contraseña</button></div>
    </form>
</div>
