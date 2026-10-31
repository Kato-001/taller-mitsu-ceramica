<?php
/**
 * CU03 · Listado de usuarios
 * @var array $usuarios @var array $roles @var array $conteo @var array $f @var Paginador $pag
 */
$titulo = 'Usuarios';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('usuarios', 14) ?> Administración</div>
        <h1>Usuarios del sistema</h1>
        <p>Cuentas de acceso y rol de cada persona del taller.</p>
    </div>
    <a href="/usuarios/nuevo" class="btn btn-primary"><?= icono('mas') ?> Nuevo usuario</a>
</div>

<div class="card anim">
    <form class="toolbar" method="get">
        <div class="input-icon">
            <?= icono('buscar') ?>
            <input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por nombre o correo" aria-label="Buscar">
        </div>
        <select class="select" name="rol" data-autoenviar aria-label="Filtrar por rol">
            <option value="">Todos los roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= (string) $f['rol'] === (string) $r['id'] ? 'selected' : '' ?>><?= e(nombre_rol($r['nombre'])) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="estado" value="<?= e($f['estado']) ?>">
        <div class="pill-tabs" role="tablist">
            <?php foreach (['' => 'Todos', 'ACTIVO' => 'Activos', 'INACTIVO' => 'Inactivos'] as $v => $l): ?>
                <a href="<?= e(url('/usuarios', ['q' => $f['q'], 'rol' => $f['rol'], 'estado' => $v])) ?>" class="<?= $f['estado'] === $v ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $conteo[$v] ?></span></a>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-outline" data-sin-carga="1">Buscar</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Usuario</th><th>Rol</th><th>Teléfono</th><th>Último acceso</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $usr): $esYo = (int) $usr['id'] === Auth::id(); ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar" <?= $usr['estado'] !== 'ACTIVO' ? 'style="filter:grayscale(1);opacity:.55"' : '' ?>><?= e(iniciales($usr['nombre_completo'])) ?></div>
                            <div><strong><?= e($usr['nombre_completo']) ?><?= $esYo ? ' <span class="faint" style="font-weight:500">(usted)</span>' : '' ?></strong><small><?= e($usr['correo']) ?></small></div>
                        </div>
                    </td>
                    <td><?= badge_rol($usr['rol']) ?></td>
                    <td class="soft"><?= e($usr['telefono'] ?: '—') ?></td>
                    <td class="soft" title="<?= e(fecha_hora($usr['ultimo_acceso'])) ?>"><?= e(hace_cuanto($usr['ultimo_acceso'])) ?></td>
                    <td><?= badge_estado($usr['estado']) ?></td>
                    <td>
                        <div class="actions">
                            <?php if ($usr['maestra_id']): ?>
                                <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/maestras/editar', ['id' => $usr['maestra_id']])) ?>" title="Ver ficha de la maestra"><?= icono('paleta', 16) ?></a>
                            <?php elseif ($usr['alumno_id']): ?>
                                <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/alumnos/ver', ['id' => $usr['alumno_id']])) ?>" title="Ver ficha del alumno"><?= icono('escuela', 16) ?></a>
                            <?php endif; ?>
                            <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/usuarios/editar', ['id' => $usr['id']])) ?>" title="Editar" aria-label="Editar a <?= e($usr['nombre_completo']) ?>"><?= icono('editar', 16) ?></a>
                            <?php if (!$esYo): $activo = $usr['estado'] === 'ACTIVO'; ?>
                                <form method="post" action="/usuarios/estado" class="inline"
                                      data-confirm="<?= $activo ? 'Desactivar cuenta' : 'Activar cuenta' ?>"
                                      data-confirm-texto="<?= e($usr['nombre_completo'] . ($activo ? ' no podrá iniciar sesión hasta que se reactive su cuenta.' : ' podrá volver a iniciar sesión.')) ?>"
                                      data-confirm-ok="<?= $activo ? 'Desactivar' : 'Activar' ?>" <?= $activo ? 'data-peligro' : '' ?>>
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $usr['id'] ?>">
                                    <button class="btn btn-ghost btn-icon btn-sm <?= $activo ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" title="<?= $activo ? 'Desactivar' : 'Activar' ?>" data-sin-carga="1"><?= icono('power', 16) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$usuarios): ?>
            <div class="empty"><?= icono('usuario-mas', 36) ?><div><strong>No se encontraron usuarios</strong></div><div class="faint">Pruebe con otro término o filtro.</div></div>
        <?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
<p class="faint" style="font-size:13px;margin-top:14px">
    Las cuentas de maestras se crean en <a href="/maestras">Maestras</a> y las de alumnos en <a href="/alumnos">Alumnos</a>. Ninguna cuenta se borra: se desactiva y conserva su historial.
</p>
