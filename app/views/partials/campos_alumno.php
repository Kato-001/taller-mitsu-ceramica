<?php
/**
 * Campos propios del alumno: CI, fecha de nacimiento y nivel.
 * @var ?array $a @var bool $conNivel
 */
?>
<div class="grid-3">
    <label class="field">
        <span>CI</span>
        <input class="input<?= clase_error('ci') ?>" name="ci" value="<?= e(old('ci', $a['ci'] ?? '')) ?>" maxlength="16" pattern="\d{5,12}(-[0-9A-Z]{1,3})?" data-msg="5 a 12 dígitos, complemento opcional (ej. 8123456-1A)." inputmode="numeric">
        <?= error_campo('ci') ?>
    </label>
    <label class="field">
        <span>Fecha de nacimiento</span>
        <input class="input<?= clase_error('fecha_nacimiento') ?>" type="date" name="fecha_nacimiento" value="<?= e(old('fecha_nacimiento', $a['fecha_nacimiento'] ?? '')) ?>" min="1920-01-01" max="<?= date('Y-m-d', strtotime('-4 years')) ?>">
        <?= error_campo('fecha_nacimiento') ?>
    </label>
    <?php if ($conNivel): ?>
        <label class="field">
            <span>Nivel *</span>
            <select class="select<?= clase_error('nivel') ?>" name="nivel" required>
                <?php foreach (array_diff_key(NIVELES, ['TODOS' => 1]) as $k => $v): ?>
                    <option value="<?= $k ?>" <?= old('nivel', $a['nivel'] ?? 'INICIAL') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <?= error_campo('nivel') ?>
        </label>
    <?php endif; ?>
</div>
