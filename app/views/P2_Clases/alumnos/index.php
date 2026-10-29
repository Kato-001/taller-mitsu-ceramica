<?php
/**
 * CU12 · Alumnos
 * @var array $alumnos @var array $f @var Paginador $pag
 */
$titulo = 'Alumnos';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('escuela', 14) ?> Clases</div>
        <h1>Alumnos</h1>
        <p>Personas que toman clases. Cada alumno puede reservar desde su portal.</p>
    </div>
    <a href="/alumnos/nuevo" class="btn btn-primary"><?= icono('usuario-mas') ?> Nuevo alumno</a>
</div>

<div class="card anim">
    <form class="toolbar" method="get">
        <div class="input-icon"><?= icono('buscar') ?>
            <input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nombre, CI o correo" aria-label="Buscar"></div>
        <select class="select" name="nivel" data-autoenviar aria-label="Nivel">
            <option value="">Todos los niveles</option>
            <?php foreach (array_diff_key(NIVELES, ['TODOS' => 1]) as $k => $v): ?><option value="<?= $k ?>" <?= $f['nivel'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="estado" data-autoenviar aria-label="Estado">
            <option value="">Activos e inactivos</option>
            <option value="ACTIVO" <?= $f['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activos</option>
            <option value="INACTIVO" <?= $f['estado'] === 'INACTIVO' ? 'selected' : '' ?>>Inactivos</option>
        </select>
        <button class="btn btn-outline" data-sin-carga="1">Buscar</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Alumno</th><th>CI</th><th>Nivel</th><th>Asistencia</th><th>Estado</th><th style="text-align:right">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($alumnos as $a): $tot = (int) $a['asistencias'] + (int) $a['faltas']; ?>
                <tr>
                    <td><div class="user-cell"><div class="avatar"><?= e(iniciales($a['nombre_completo'])) ?></div>
                        <div><a href="<?= e(url('/alumnos/ver', ['id' => $a['id']])) ?>"><strong><?= e($a['nombre_completo']) ?></strong></a><small><?= e($a['correo']) ?></small></div></div></td>
                    <td class="mono"><?= e($a['ci'] ?: '—') ?></td>
                    <td><?= badge(NIVELES[$a['nivel']] ?? $a['nivel'], 'badge-info', false) ?></td>
                    <td><?= $tot ? round($a['asistencias'] / $tot * 100) . ' %' : '—' ?> <span class="faint" style="font-size:12px">(<?= (int) $a['asistencias'] ?>/<?= $tot ?>)</span></td>
                    <td><?= badge_estado($a['estado']) ?></td>
                    <td><div class="actions">
                        <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/alumnos/ver', ['id' => $a['id']])) ?>" title="Ver ficha y reservar"><?= icono('ver', 16) ?></a>
                        <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/alumnos/editar', ['id' => $a['id']])) ?>" title="Editar"><?= icono('editar', 16) ?></a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$alumnos): ?><div class="empty"><?= icono('escuela', 36) ?><div><strong>No se encontraron alumnos</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
