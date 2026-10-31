<?php
/**
 * Contraseña y confirmación (obligatorias al registrar, opcionales al editar).
 * @var bool $editando
 */
?>
<div class="grid-2">
    <label class="field">
        <span>Contraseña <?= $editando ? '(vacío = no cambiar)' : '*' ?></span>
        <input class="input<?= clase_error('password') ?>" type="password" name="password" <?= $editando ? '' : 'required' ?> minlength="8" maxlength="100"
               pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" data-msg="Mínimo 8 caracteres con letras y números." autocomplete="new-password" data-fortaleza="fuerza">
        <div class="strength" id="fuerza" hidden><i></i><i></i><i></i><i></i></div>
        <?= error_campo('password') ?: '<small>Mínimo 8 caracteres con letras y números. Se guarda cifrada (password_hash).</small>' ?>
    </label>
    <label class="field">
        <span>Confirmar contraseña</span>
        <input class="input<?= clase_error('password_confirmacion') ?>" type="password" name="password_confirmacion" maxlength="100" data-coincide="password" autocomplete="new-password">
        <?= error_campo('password_confirmacion') ?>
    </label>
</div>
