<?php
/**
 * CU07 · Registrar / editar maestra
 * @var ?array $maestra
 */
$editando = $maestra !== null;
$titulo = $editando ? 'Editar maestra' : 'Nueva maestra';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/maestras"><?= icono('izquierda', 14) ?> Maestras</a></div>
        <h1><?= $editando ? e($maestra['nombre_completo']) : 'Registrar maestra' ?></h1>
        <p>Se crea también su cuenta de acceso con el rol Maestra.</p>
    </div>
</div>

<form method="post" action="/maestras/guardar" class="card card-pad anim" style="max-width:820px" data-validar>
    <?= csrf_field() ?>
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $maestra['id'] ?>"><?php endif; ?>
    <div class="section-title">Datos personales</div>
    <?= View::parcial('campos_persona', ['p' => $maestra]) ?>

    <div class="section-title mt">Perfil docente</div>
    <div class="grid-2">
        <label class="field">
            <span>Especialidad *</span>
            <input class="input<?= clase_error('especialidad') ?>" name="especialidad" value="<?= e(old('especialidad', $maestra['especialidad'] ?? '')) ?>" required minlength="3" maxlength="120" placeholder="Ej. Torno alfarero">
            <?= error_campo('especialidad') ?>
        </label>
        <label class="field span-2">
            <span>Breve presentación</span>
            <textarea class="textarea" name="biografia" rows="3" maxlength="500" placeholder="Técnicas que enseña, experiencia…"><?= e(old('biografia', $maestra['biografia'] ?? '')) ?></textarea>
            <?= error_campo('biografia') ?>
        </label>
    </div>

    <div class="section-title mt">Acceso</div>
    <?= View::parcial('campos_clave', ['editando' => $editando]) ?>

    <div class="row mt" style="justify-content:flex-end">
        <a href="/maestras" class="btn btn-ghost">Cancelar</a>
        <button class="btn btn-primary"><?= icono('check') ?> <?= $editando ? 'Guardar cambios' : 'Registrar maestra' ?></button>
    </div>
</form>
