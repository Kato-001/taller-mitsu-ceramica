<?php
/**
 * Portal del alumno · inicio: próximas clases e historial
 * @var array $alumno @var array $proximas @var array $historial
 */
$titulo = 'Mi espacio';
$tot = (int) $alumno['asistencias'] + (int) $alumno['faltas'];
$horas = (int) config('HORAS_CANCELACION', '12');
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('vasija', 14) ?> Portal del alumno</div>
        <h1>Hola, <?= e($alumno['nombre']) ?></h1>
        <p>Nivel <?= e(mb_strtolower(NIVELES[$alumno['nivel']] ?? '')) ?> · <?= $tot ? 'asististe a ' . (int) $alumno['asistencias'] . ' de ' . $tot . ' clases' : 'aún no tomaste clases' ?>.</p>
    </div>
    <a href="/portal/reservar" class="btn btn-primary"><?= icono('calendario-mas') ?> Reservar clase</a>
</div>

<h2 style="font-size:18px;margin-bottom:12px">Mis próximas clases</h2>
<div class="clases-lista" data-escalonar>
    <?php foreach ($proximas as $r): $cancelable = strtotime($r['fecha'] . ' ' . $r['hora_inicio']) - time() >= $horas * 3600; ?>
        <div class="clase-card">
            <span class="fecha-chip"><?= e(fecha_larga($r['fecha'])) ?> · <?= hora($r['hora_inicio']) ?>–<?= hora($r['hora_fin']) ?></span>
            <strong style="font-size:17px;font-family:var(--serif)"><?= e($r['taller']) ?></strong>
            <span class="soft">Con <?= e($r['maestra']) ?></span>
            <?php if ($cancelable): ?>
                <form method="post" action="/portal/cancelar" class="inline" data-confirm="¿Cancelar tu reserva?" data-confirm-texto="<?= e($r['taller'] . ' · ' . fecha_larga($r['fecha'])) ?>. Tu cupo quedará libre." data-confirm-ok="Sí, cancelar" data-peligro>
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-ghost btn-sm btn-danger-ghost" data-sin-carga="1"><?= icono('x-circulo', 15) ?> Cancelar reserva</button>
                </form>
            <?php else: ?>
                <small class="faint">Ya no se puede cancelar en línea (menos de <?= $horas ?> h). Avisa al taller.</small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$proximas): ?>
        <div class="card card-pad empty"><?= icono('calendario', 36) ?><div><strong>No tienes clases reservadas</strong></div>
            <a href="/portal/reservar" class="btn btn-primary btn-sm" style="margin-top:10px">Ver clases disponibles</a></div>
    <?php endif; ?>
</div>

<div class="card mt anim">
    <div class="card-head"><h2>Mi historial reciente</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Taller</th><th>Maestra</th><th>Resultado</th></tr></thead>
            <tbody>
            <?php foreach ($historial as $r): ?>
                <tr>
                    <td><?= e(fecha($r['fecha'])) ?></td>
                    <td><?= e($r['taller']) ?></td>
                    <td><?= e($r['maestra']) ?></td>
                    <td><?= badge_estado_op($r['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$historial): ?><div class="empty">Aquí verás tus clases pasadas.</div><?php endif; ?>
    </div>
</div>
