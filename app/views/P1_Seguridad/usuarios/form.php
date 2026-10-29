<?php
/**
 * CU03 · Registrar / editar usuario
 * @var ?array $usuario @var array $roles
 */
$editando = $usuario !== null;
$titulo = $editando ? 'Editar usuario' : 'Nuevo usuario';
$fijo = $editando && ($usuario['maestra_id'] || $usuario['alumno_id'] || (int) $usuario['id'] === Auth::id());
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/usuarios"><?= icono('izquierda', 14) ?> Usuarios</a></div>
        <h1><?= $editando ? 'Editar usuario' : 'Registrar usuario' ?></h1>
        <p><?= $editando ? e($usuario['correo']) : 'Para personal del taller (administración y ventas). Los campos con * son obligatorios.' ?></p>
    </div>
</div>

<form method="post" action="/usuarios/guardar" class="card card-pad anim" style="max-width:820px" data-validar>
    <?= csrf_field() ?>
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $usuario['id'] ?>"><?php endif; ?>

    <div class="section-title">Datos de la persona</div>
    <?= View::parcial('campos_persona', ['p' => $usuario]) ?>

    <div class="section-title mt">Acceso</div>
    <div class="grid-2">
        <label class="field">
            <span>Rol *</span>
            <select class="select<?= clase_error('rol_id') ?>" name="rol_id" required <?= $fijo ? 'disabled' : '' ?>>
                <option value="">Seleccione…</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= (string) old('rol_id', $usuario['rol_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e(nombre_rol($r['nombre'])) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($fijo): ?>
                <input type="hidden" name="rol_id" value="<?= $usuario['rol_id'] ?>">
                <small><?= (int) $usuario['id'] === Auth::id() ? 'No puede cambiar su propio rol.' : 'El rol de maestras y alumnos está ligado a su ficha.' ?></small>
            <?php endif; ?>
            <?= error_campo('rol_id') ?>
        </label>
    </div>
    <?= View::parcial('campos_clave', ['editando' => $editando]) ?>

    <div class="row mt" style="justify-content:flex-end">
        <a href="/usuarios" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icono('check') ?> <?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
</form>
