<?php
/**
 * CU05 · Consultar bitácora
 * @var array $registros @var array $acciones @var array $modulos @var array $f @var Paginador $pag
 */
$titulo = 'Bitácora';
$colores = ['LOGIN_FALLIDO' => 'badge-danger', 'ANULAR' => 'badge-danger', 'CANCELAR' => 'badge-warn', 'DESACTIVAR' => 'badge-warn',
            'CREAR' => 'badge-ok', 'VENDER' => 'badge-ok', 'RESERVAR' => 'badge-accent', 'INICIAR_SESION' => 'badge-info'];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('bitacora', 14) ?> Administración</div>
        <h1>Bitácora del sistema</h1>
        <p>Quién hizo qué, cuándo y desde qué IP. Los registros no se pueden editar ni borrar.</p>
    </div>
</div>

<div class="card anim">
    <form class="toolbar" method="get">
        <div class="input-icon"><?= icono('buscar') ?>
            <input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar en el detalle o por usuario" aria-label="Buscar"></div>
        <select class="select" name="modulo" data-autoenviar aria-label="Módulo">
            <option value="">Todos los módulos</option>
            <?php foreach ($modulos as $m): ?><option <?= $f['modulo'] === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="accion" data-autoenviar aria-label="Acción">
            <option value="">Todas las acciones</option>
            <?php foreach ($acciones as $a): ?><option <?= $f['accion'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select>
        <input class="input" type="date" name="desde" value="<?= e($f['desde']) ?>" aria-label="Desde" style="max-width:160px">
        <input class="input" type="date" name="hasta" value="<?= e($f['hasta']) ?>" aria-label="Hasta" style="max-width:160px">
        <button class="btn btn-outline" data-sin-carga="1"><?= icono('filtro', 16) ?> Filtrar</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($registros as $b): ?>
                <tr>
                    <td class="mono" style="white-space:nowrap"><?= e(date('d/m/Y H:i:s', strtotime($b['fecha']))) ?></td>
                    <td><?= $b['usuario'] ? '<strong>' . e($b['usuario']) . '</strong><div class="faint" style="font-size:12px">' . e($b['correo']) . '</div>' : '<span class="faint">Sistema / anónimo</span>' ?></td>
                    <td><?= badge($b['accion'], $colores[$b['accion']] ?? 'badge-off', false) ?></td>
                    <td class="soft"><?= e($b['modulo']) ?></td>
                    <td><?= e($b['detalle']) ?></td>
                    <td class="mono faint"><?= e($b['ip'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$registros): ?><div class="empty"><?= icono('bitacora', 36) ?><div><strong>Sin registros para estos filtros</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
