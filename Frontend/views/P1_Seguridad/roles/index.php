<?php
/**
 * CU04 · Roles y matriz de permisos
 * @var array $roles @var array $permisos (agrupados por módulo) @var array $matriz
 */
$titulo = 'Roles y permisos';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('escudo', 14) ?> Administración</div>
        <h1>Roles y permisos</h1>
        <p>Cada usuario tiene un rol; cada rol tiene permisos que definen su menú y lo que puede hacer. El servidor verifica el permiso en cada acción.</p>
    </div>
</div>

<div class="roles-layout">
    <div class="stack">
        <form method="post" action="/roles/guardar" class="card card-pad anim" data-validar>
            <?= csrf_field() ?>
            <h2 style="font-size:17px;margin-bottom:16px">Nuevo rol</h2>
            <div class="stack">
                <label class="field">
                    <span>Nombre *</span>
                    <input class="input<?= clase_error('nombre') ?>" name="nombre" value="<?= e(old('nombre')) ?>" required minlength="3" maxlength="40"
                           pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ_ ]+" data-msg="Solo letras." placeholder="Ej. CAJERO">
                    <?= error_campo('nombre') ?: '<small>Se guarda en mayúsculas.</small>' ?>
                </label>
                <label class="field">
                    <span>Descripción</span>
                    <textarea class="textarea" name="descripcion" rows="2" maxlength="200"><?= e(old('descripcion')) ?></textarea>
                </label>
                <button class="btn btn-primary"><?= icono('mas') ?> Agregar rol</button>
            </div>
        </form>

        <div class="card anim">
            <div class="card-head"><h2>Roles</h2><span class="faint" style="font-size:13px"><?= count($roles) ?> roles</span></div>
            <?php foreach ($roles as $r): $sistema = in_array($r['nombre'], Rol::DEL_SISTEMA, true); ?>
                <div class="role-card" <?= $r['estado'] !== 'ACTIVO' ? 'style="opacity:.6"' : '' ?>>
                    <div class="grow">
                        <h3><?= badge_rol($r['nombre']) ?>
                            <?php if ($sistema): ?><span class="badge badge-lock"><?= icono('candado', 11) ?> Sistema</span><?php endif; ?>
                            <?php if ($r['estado'] !== 'ACTIVO'): ?><?= badge_estado($r['estado']) ?><?php endif; ?>
                            <span class="faint" style="font-size:13px;font-weight:500"><?= (int) $r['usuarios'] ?> usuario(s)</span>
                        </h3>
                        <form method="post" action="/roles/guardar" class="inline-edit">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input class="input" name="descripcion" value="<?= e($r['descripcion']) ?>" maxlength="200" aria-label="Descripción de <?= e($r['nombre']) ?>" placeholder="Sin descripción">
                            <button class="btn btn-ghost btn-icon btn-sm" title="Guardar descripción" data-sin-carga="1"><?= icono('check', 16) ?></button>
                        </form>
                    </div>
                    <?php if (!$sistema): $act = $r['estado'] === 'ACTIVO'; ?>
                        <form method="post" action="/roles/estado" class="inline" data-confirm="<?= $act ? 'Desactivar' : 'Activar' ?> el rol <?= e($r['nombre']) ?>"
                              data-confirm-texto="<?= $act ? 'Solo se puede desactivar si no tiene usuarios activos.' : 'Podrá volver a asignarse a usuarios.' ?>" data-confirm-ok="<?= $act ? 'Desactivar' : 'Activar' ?>" <?= $act ? 'data-peligro' : '' ?>>
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-ghost btn-icon btn-sm <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" title="<?= $act ? 'Desactivar' : 'Activar' ?>" data-sin-carga="1"><?= icono('power', 16) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="card-pad" style="padding-top:14px;border-top:1px solid var(--border)">
                <p class="faint m0" style="font-size:13px">Los roles del sistema no se desactivan. Ningún rol se borra: se desactiva para conservar el historial.</p>
            </div>
        </div>
    </div>

    <div class="card anim">
        <div class="card-head"><h2>Permisos por rol</h2><span class="faint" style="font-size:13px">Marque y guarde cada columna</span></div>
        <div class="table-wrap">
            <table class="table matrix">
                <thead>
                <tr>
                    <th>Permiso</th>
                    <?php foreach ($roles as $r): ?><th><?= e(mb_strtoupper(mb_substr(nombre_rol($r['nombre']), 0, 9))) ?></th><?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($permisos as $modulo => $lista): ?>
                    <tr class="mod"><td colspan="<?= count($roles) + 1 ?>"><?= e($modulo) ?></td></tr>
                    <?php foreach ($lista as $p): ?>
                        <tr>
                            <td><?= e($p['descripcion']) ?><small><?= e($p['codigo']) ?></small></td>
                            <?php foreach ($roles as $r):
                                $marcado = in_array((int) $p['id'], $matriz[(int) $r['id']] ?? [], true);
                                $fijo = $r['nombre'] === 'ADMINISTRADOR' && in_array($p['codigo'], Rol::PERMISOS_ADMIN_FIJOS, true); ?>
                                <td><input type="checkbox" form="permisos-<?= $r['id'] ?>" name="permisos[]" value="<?= $p['id'] ?>"
                                           <?= $marcado ? 'checked' : '' ?> <?= $fijo ? 'disabled title="El administrador siempre conserva este permiso"' : '' ?>
                                           aria-label="<?= e($p['descripcion'] . ' para ' . $r['nombre']) ?>"></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <tr>
                    <td></td>
                    <?php foreach ($roles as $r): ?>
                        <td>
                            <form method="post" action="/roles/permisos" id="permisos-<?= $r['id'] ?>">
                                <?= csrf_field() ?><input type="hidden" name="rol_id" value="<?= $r['id'] ?>">
                                <button class="btn btn-outline btn-sm">Guardar</button>
                            </form>
                        </td>
                    <?php endforeach; ?>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
