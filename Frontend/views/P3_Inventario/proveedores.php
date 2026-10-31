<?php
/**
 * CU14 · Proveedores
 * @var array $proveedores @var ?array $editar
 */
$titulo = 'Proveedores';
$p = $editar;
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('tienda', 14) ?> Inventario</div>
        <h1>Proveedores</h1>
        <p>Empresas mineras e insumos cerámicos (Cochabamba, La Paz y Santa Cruz). Datos de demostración.</p>
    </div>
</div>
<div class="roles-layout">
    <form method="post" action="/proveedores/guardar" class="card card-pad anim stack" data-validar>
        <?= csrf_field() ?>
        <?php if ($p): ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><?php endif; ?>
        <h2 style="font-size:17px"><?= $p ? 'Editar proveedor' : 'Nuevo proveedor' ?></h2>
        <label class="field"><span>Razón social *</span>
            <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre', $p['nombre'] ?? '')) ?>" required minlength="3" maxlength="100"><?= error_campo('nombre') ?></label>
        <div class="grid-2">
            <label class="field"><span>NIT</span>
                <input class="input<?= clase_error('nit') ?>" name="nit" value="<?= e(old('nit', $p['nit'] ?? '')) ?>" pattern="\d{5,15}" data-msg="5 a 15 dígitos." maxlength="15" inputmode="numeric"><?= error_campo('nit') ?></label>
            <label class="field"><span>Ciudad *</span>
                <input class="input<?= clase_error('ciudad') ?>" name="ciudad" value="<?= e(old('ciudad', $p['ciudad'] ?? '')) ?>" required minlength="3" maxlength="40" list="ciudades"><?= error_campo('ciudad') ?></label>
            <label class="field"><span>Contacto</span>
                <input class="input" name="contacto" value="<?= e(old('contacto', $p['contacto'] ?? '')) ?>" maxlength="80"></label>
            <label class="field"><span>Teléfono</span>
                <input class="input<?= clase_error('telefono') ?>" type="tel" name="telefono" value="<?= e(old('telefono', $p['telefono'] ?? '')) ?>" pattern="[0-9+\- ]{7,20}" data-msg="7 a 20 dígitos." maxlength="20"><?= error_campo('telefono') ?></label>
            <label class="field span-2"><span>Correo</span>
                <input class="input<?= clase_error('correo') ?>" type="email" name="correo" value="<?= e(old('correo', $p['correo'] ?? '')) ?>" maxlength="120"><?= error_campo('correo') ?></label>
            <label class="field span-2"><span>Dirección</span>
                <input class="input" name="direccion" value="<?= e(old('direccion', $p['direccion'] ?? '')) ?>" maxlength="200"></label>
        </div>
        <datalist id="ciudades"><option>Santa Cruz</option><option>Cochabamba</option><option>La Paz</option><option>Sucre</option><option>Tarija</option><option>Oruro</option><option>Potosí</option></datalist>
        <div class="row" style="justify-content:flex-end">
            <?php if ($p): ?><a href="/proveedores" class="btn btn-ghost">Cancelar</a><?php endif; ?>
            <button class="btn btn-primary"><?= icono('check') ?> <?= $p ? 'Guardar' : 'Agregar proveedor' ?></button>
        </div>
    </form>

    <div class="card anim">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Proveedor</th><th>Ciudad</th><th style="text-align:right">Comprado</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($proveedores as $x): $act = $x['estado'] === 'ACTIVO'; ?>
                    <tr <?= $act ? '' : 'style="opacity:.55"' ?>>
                        <td><strong><?= e($x['nombre']) ?></strong><div class="faint" style="font-size:12.5px"><?= e(implode(' · ', array_filter([$x['nit'] ? 'NIT ' . $x['nit'] : '', $x['contacto'], $x['telefono']]))) ?></div></td>
                        <td><?= e($x['ciudad']) ?></td>
                        <td style="text-align:right"><a href="<?= e(url('/compras', ['proveedor' => $x['id']])) ?>"><?= bs($x['total_comprado']) ?></a><div class="faint" style="font-size:12px"><?= (int) $x['compras'] ?> compra(s)</div></td>
                        <td><?= badge_estado($x['estado']) ?></td>
                        <td><div class="actions">
                            <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/proveedores', ['editar' => $x['id']])) ?>" title="Editar"><?= icono('editar', 16) ?></a>
                            <form method="post" action="/proveedores/estado" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
                                <button class="btn btn-ghost btn-icon btn-sm <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" title="<?= $act ? 'Desactivar' : 'Activar' ?>" data-sin-carga="1"><?= icono('power', 16) ?></button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
