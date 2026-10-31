<?php
/**
 * Registro público de alumnos (crea su cuenta para el portal)
 * @var array $talleres
 */
$titulo = 'Crear cuenta';
?>
<div class="auth-centro" style="padding:32px 16px">
    <div class="card card-pad auth-tarjeta anim" style="max-width:760px">
        <a href="/login" class="brand" style="padding:0 0 18px">
            <span class="brand-logo"><?= icono('vasija', 22) ?></span>
            <span><strong>Mitsü</strong><small><?= e(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü')) ?></small></span>
        </a>
        <h1 style="font-size:28px">Crea tu cuenta de alumno</h1>
        <p class="soft" style="margin:8px 0 18px">Con tu cuenta puedes ver los horarios, reservar tu lugar y revisar tu asistencia.</p>

        <div class="chips" style="margin-bottom:18px">
            <?php foreach (array_slice($talleres, 0, 6) as $t): ?><span class="badge badge-accent"><?= e($t['nombre']) ?></span><?php endforeach; ?>
        </div>

        <form method="post" action="/registro" class="stack" data-validar>
            <?= csrf_field() ?>
            <?= View::parcial('campos_persona', ['p' => null]) ?>
            <?= View::parcial('campos_alumno', ['a' => null, 'conNivel' => false]) ?>
            <?= View::parcial('campos_clave', ['editando' => false]) ?>
            <label class="check" style="font-size:14px">
                <input type="checkbox" name="acepta" value="1" required <?= old('acepta') ? 'checked' : '' ?>>
                <span>Acepto que el taller use mis datos solo para gestionar mis reservas y comunicarse conmigo.</span>
            </label>
            <?= error_campo('acepta') ?>
            <button class="btn btn-primary btn-block" style="height:48px"><?= icono('usuario-mas', 17) ?> Crear mi cuenta</button>
        </form>
        <p class="faint" style="font-size:13px;margin:16px 0 0;text-align:center">¿Ya tienes cuenta? <a href="/login">Ingresa aquí</a></p>
    </div>
</div>
