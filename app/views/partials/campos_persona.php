<?php
/**
 * Campos comunes de una persona: nombre, apellido, correo y teléfono.
 * @var ?array $p datos actuales (null al registrar)
 */
?>
<div class="grid-2">
    <label class="field">
        <span>Nombre *</span>
        <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre', $p['nombre'] ?? '')) ?>" required minlength="2" maxlength="60"
               pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' .\-]+" data-msg="Solo letras." autocomplete="given-name">
        <?= error_campo('nombre') ?>
    </label>
    <label class="field">
        <span>Apellido *</span>
        <input class="input<?= clase_error('apellido') ?>" name="apellido" value="<?= e(old('apellido', $p['apellido'] ?? '')) ?>" required minlength="2" maxlength="60"
               pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' .\-]+" data-msg="Solo letras." autocomplete="family-name">
        <?= error_campo('apellido') ?>
    </label>
    <label class="field">
        <span>Correo electrónico *</span>
        <input class="input<?= clase_error('correo') ?>" type="email" name="correo" value="<?= e(old('correo', $p['correo'] ?? '')) ?>" required maxlength="120" autocomplete="email">
        <?= error_campo('correo') ?: '<small>Con este correo inicia sesión y recupera su contraseña.</small>' ?>
    </label>
    <label class="field">
        <span>Teléfono</span>
        <input class="input<?= clase_error('telefono') ?>" type="tel" name="telefono" value="<?= e(old('telefono', $p['telefono'] ?? '')) ?>" maxlength="20"
               pattern="[0-9+\- ]{7,20}" data-msg="De 7 a 20 dígitos." autocomplete="tel" placeholder="Ej. 70012345">
        <?= error_campo('telefono') ?>
    </label>
</div>
