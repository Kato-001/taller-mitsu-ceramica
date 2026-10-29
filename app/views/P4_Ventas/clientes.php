<?php
/**
 * Clientes que compran piezas (parte del CU18)
 * @var array $clientes @var ?array $editar @var string $q
 */
$titulo = 'Clientes';
$c = $editar;
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('usuarios', 14) ?> Ventas</div>
        <h1>Clientes</h1>
        <p>"Cliente general" se usa para ventas sin datos. Los clientes no se borran: se desactivan.</p>
    </div>
</div>
<div class="roles-layout">
    <form method="post" action="/clientes/guardar" class="card card-pad anim stack" data-validar>
        <?= csrf_field() ?>
        <?php if ($c): ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><?php endif; ?>
        <h2 style="font-size:17px"><?= $c ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
        <label class="field"><span>Nombre o razón social *</span>
            <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre', $c['nombre'] ?? '')) ?>" required minlength="3" maxlength="120"><?= error_campo('nombre') ?></label>
        <label class="field"><span>CI / NIT</span>
            <input class="input<?= clase_error('ci_nit') ?>" name="ci_nit" value="<?= e(old('ci_nit', $c['ci_nit'] ?? '')) ?>" maxlength="20" pattern="\d{4,15}(-[0-9A-Z]{1,3})?" data-msg="Solo números (4 a 15)."><?= error_campo('ci_nit') ?></label>
        <div class="grid-2">
            <label class="field"><span>Teléfono</span>
                <input class="input<?= clase_error('telefono') ?>" type="tel" name="telefono" value="<?= e(old('telefono', $c['telefono'] ?? '')) ?>" maxlength="20" pattern="[0-9+\- ]{7,20}" data-msg="7 a 20 dígitos."><?= error_campo('telefono') ?></label>
            <label class="field"><span>Correo</span>
                <input class="input<?= clase_error('correo') ?>" type="email" name="correo" value="<?= e(old('correo', $c['correo'] ?? '')) ?>" maxlength="120"><?= error_campo('correo') ?></label>
        </div>
        <div class="row" style="justify-content:flex-end">
            <?php if ($c): ?><a href="/clientes" class="btn btn-ghost">Cancelar</a><?php endif; ?>
            <button class="btn btn-primary"><?= icono('check') ?> <?= $c ? 'Guardar' : 'Agregar cliente' ?></button>
        </div>
    </form>
    <div class="card anim">
        <form class="toolbar" method="get">
            <div class="input-icon"><?= icono('buscar') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre o CI/NIT" aria-label="Buscar"></div>
            <button class="btn btn-outline" data-sin-carga="1">Buscar</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Cliente</th><th>CI / NIT</th><th style="text-align:right">Compras</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($clientes as $x): $act = $x['estado'] === 'ACTIVO'; ?>
                    <tr <?= $act ? '' : 'style="opacity:.55"' ?>>
                        <td><strong><?= e($x['nombre']) ?></strong><div class="faint" style="font-size:12.5px"><?= e(implode(' · ', array_filter([$x['telefono'], $x['correo']]))) ?></div></td>
                        <td class="mono"><?= e($x['ci_nit'] ?: '—') ?></td>
                        <td style="text-align:right"><?= bs($x['total']) ?><div class="faint" style="font-size:12px"><?= (int) $x['compras'] ?> compra(s)</div></td>
                        <td><?= badge_estado($x['estado']) ?></td>
                        <td><div class="actions">
                            <?php if ((int) $x['id'] !== 1): ?>
                                <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/clientes', ['editar' => $x['id']])) ?>" title="Editar"><?= icono('editar', 16) ?></a>
                                <form method="post" action="/clientes/estado" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
                                    <button class="btn btn-ghost btn-icon btn-sm <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" title="<?= $act ? 'Desactivar' : 'Activar' ?>" data-sin-carga="1"><?= icono('power', 16) ?></button></form>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
