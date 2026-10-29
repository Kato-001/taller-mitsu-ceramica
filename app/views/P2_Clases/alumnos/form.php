<?php
/**
 * CU12 · Registrar / editar alumno
 * @var ?array $alumno
 */
$editando = $alumno !== null;
$titulo = $editando ? 'Editar alumno' : 'Nuevo alumno';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/alumnos"><?= icono('izquierda', 14) ?> Alumnos</a></div>
        <h1><?= $editando ? e($alumno['nombre_completo']) : 'Registrar alumno' ?></h1>
        <p>El alumno recibe una cuenta para reservar sus clases desde el portal.</p>
    </div>
</div>
<form method="post" action="/alumnos/guardar" class="card card-pad anim" style="max-width:860px" data-validar>
    <?= csrf_field() ?>
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $alumno['id'] ?>"><?php endif; ?>
    <div class="section-title">Datos personales</div>
    <?= View::parcial('campos_persona', ['p' => $alumno]) ?>
    <?= View::parcial('campos_alumno', ['a' => $alumno, 'conNivel' => true]) ?>
    <div class="section-title mt">Acceso al portal</div>
    <?= View::parcial('campos_clave', ['editando' => $editando]) ?>
    <div class="row mt" style="justify-content:flex-end">
        <a href="<?= $editando ? e(url('/alumnos/ver', ['id' => $alumno['id']])) : '/alumnos' ?>" class="btn btn-ghost">Cancelar</a>
        <button class="btn btn-primary"><?= icono('check') ?> <?= $editando ? 'Guardar cambios' : 'Registrar alumno' ?></button>
    </div>
</form>
