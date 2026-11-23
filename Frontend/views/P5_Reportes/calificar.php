<?php
/**
 * CU26 · Calificar el servicio (pedido entregado o clase asistida)
 * @var string $tipo @var array $servicio
 */
$titulo = 'Calificar el servicio';
$textos = [5 => 'Excelente', 4 => 'Muy bueno', 3 => 'Bueno', 2 => 'Regular', 1 => 'Malo'];
?>
<div class="page-head"><div>
    <div class="eyebrow"><a href="<?= $tipo === 'PEDIDO' ? '/mis-pedidos' : '/portal' ?>"><?= icono('izquierda', 14) ?> Volver</a></div>
    <h1>Califica nuestro servicio</h1>
    <p><?= e($servicio['nombre']) ?> · <?= e(fecha($servicio['fecha'])) ?></p>
</div></div>

<form method="post" action="/calificar" class="card card-pad anim stack" style="max-width:560px" data-validar>
    <?= csrf_field() ?>
    <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
    <input type="hidden" name="id" value="<?= (int) $servicio['id'] ?>">
    <fieldset class="field" style="border:0;padding:0;margin:0"><legend>¿Cómo fue tu experiencia? *</legend>
        <div class="elige-estrellas">
            <?php foreach ($textos as $n => $t): ?>
                <input type="radio" name="puntaje" id="p<?= $n ?>" value="<?= $n ?>" required <?= old('puntaje') === (string) $n ? 'checked' : '' ?>><label for="p<?= $n ?>" title="<?= $t ?>"><span aria-hidden="true">★</span><span class="sr-only"><?= $n ?> de 5: <?= $t ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= error_campo('puntaje') ?>
    </fieldset>
    <label class="field"><span>Comentario (opcional)</span>
        <textarea class="textarea" name="comentario" rows="4" maxlength="400" placeholder="Cuéntanos qué te gustó o qué podemos mejorar"><?= e(old('comentario')) ?></textarea><?= error_campo('comentario') ?></label>
    <div class="row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icono('check') ?> Enviar calificación</button></div>
</form>
