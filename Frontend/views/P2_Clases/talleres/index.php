<?php
/**
 * CU08 · Talleres (cursos) que ofrece el estudio
 * @var array $talleres @var ?array $editar
 */
$titulo = 'Talleres';
$t = $editar;
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('paleta', 14) ?> Clases</div>
        <h1>Talleres</h1>
        <p>Cursos del estudio con su nivel, público y precios. Los precios de la demostración son estimados.</p>
    </div>
    <a href="/horarios" class="btn btn-outline"><?= icono('reloj') ?> Ver horarios</a>
</div>

<div class="roles-layout">
    <form method="post" action="/talleres/guardar" class="card card-pad anim stack" data-validar>
        <?= csrf_field() ?>
        <?php if ($t): ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><?php endif; ?>
        <h2 style="font-size:17px"><?= $t ? 'Editar taller' : 'Nuevo taller' ?></h2>
        <label class="field"><span>Nombre *</span>
            <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre', $t['nombre'] ?? '')) ?>" required minlength="3" maxlength="80">
            <?= error_campo('nombre') ?></label>
        <label class="field"><span>Descripción *</span>
            <textarea class="textarea<?= clase_error('descripcion') ?>" name="descripcion" rows="3" required minlength="10" maxlength="500"><?= e(old('descripcion', $t['descripcion'] ?? '')) ?></textarea>
            <?= error_campo('descripcion') ?></label>
        <div class="grid-2">
            <label class="field"><span>Nivel *</span>
                <select class="select" name="nivel" required>
                    <?php foreach (NIVELES as $k => $v): ?><option value="<?= $k ?>" <?= old('nivel', $t['nivel'] ?? 'INICIAL') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select></label>
            <label class="field"><span>Público *</span>
                <select class="select" name="publico" required>
                    <?php foreach (PUBLICOS as $k => $v): ?><option value="<?= $k ?>" <?= old('publico', $t['publico'] ?? 'ADULTOS') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select></label>
            <label class="field"><span>Duración (min) *</span>
                <input class="input<?= clase_error('duracion_min') ?>" type="number" name="duracion_min" value="<?= e(old('duracion_min', $t['duracion_min'] ?? '120')) ?>" required min="30" max="300" step="5">
                <?= error_campo('duracion_min') ?></label>
            <label class="field"><span>Precio por clase (Bs) *</span>
                <input class="input<?= clase_error('precio_clase') ?>" type="number" name="precio_clase" value="<?= e(old('precio_clase', $t['precio_clase'] ?? '')) ?>" required min="1" max="5000" step="0.5">
                <?= error_campo('precio_clase') ?></label>
            <label class="field span-2"><span>Precio mensual (Bs) *</span>
                <input class="input<?= clase_error('precio_mensual') ?>" type="number" name="precio_mensual" value="<?= e(old('precio_mensual', $t['precio_mensual'] ?? '')) ?>" required min="1" max="20000" step="0.5">
                <?= error_campo('precio_mensual') ?></label>
        </div>
        <div class="row" style="justify-content:flex-end">
            <?php if ($t): ?><a href="/talleres" class="btn btn-ghost">Cancelar</a><?php endif; ?>
            <button class="btn btn-primary"><?= icono('check') ?> <?= $t ? 'Guardar' : 'Agregar taller' ?></button>
        </div>
    </form>

    <div class="clases-lista" data-escalonar style="align-content:start">
        <?php foreach ($talleres as $x): $act = $x['estado'] === 'ACTIVO'; ?>
            <div class="clase-card" <?= $act ? '' : 'style="opacity:.6"' ?>>
                <div class="row-between"><span class="fecha-chip"><?= e(PUBLICOS[$x['publico']]) ?> · <?= e(NIVELES[$x['nivel']]) ?></span><?= badge_estado($x['estado']) ?></div>
                <strong style="font-size:17px;font-family:var(--serif)"><?= e($x['nombre']) ?></strong>
                <p class="soft m0" style="font-size:13.5px"><?= e($x['descripcion']) ?></p>
                <div class="row-between" style="font-size:13.5px">
                    <span><strong><?= bs($x['precio_clase']) ?></strong> clase · <strong><?= bs($x['precio_mensual']) ?></strong> mes</span>
                    <span class="faint"><?= (int) $x['duracion_min'] ?> min · <?= (int) $x['horarios'] ?> horario(s)</span>
                </div>
                <div class="row" style="justify-content:flex-end">
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/talleres', ['editar' => $x['id']])) ?>"><?= icono('editar', 15) ?> Editar</a>
                    <form method="post" action="/talleres/estado" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
                        <button class="btn btn-ghost btn-sm <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" data-sin-carga="1"><?= icono('power', 15) ?> <?= $act ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
