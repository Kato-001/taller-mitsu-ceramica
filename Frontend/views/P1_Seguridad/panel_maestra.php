<?php
/**
 * Panel de la maestra: sus clases de hoy, de la semana y asistencias pendientes.
 * @var array $hoy @var array $semana @var array $pendientes
 */
$titulo = 'Mi panel';
$u = Auth::usuario();
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('paleta', 14) ?> Maestra</div>
        <h1>Hola, <?= e($u['nombre']) ?></h1>
        <p>Tus clases de hoy, <?= e(fecha_larga(hoy())) ?>.</p>
    </div>
    <a href="/clases" class="btn btn-primary"><?= icono('agenda') ?> Mis clases</a>
</div>

<?php if ($pendientes): ?>
    <div class="alert alert-warn anim"><?= icono('alerta') ?>
        <div>Tienes <strong><?= count($pendientes) ?></strong> clase(s) pasadas con asistencia sin marcar:
            <?php foreach ($pendientes as $i => $p): ?><?= $i ? ', ' : ' ' ?><a href="<?= e(url('/clases/ver', ['id' => $p['id']])) ?>"><?= e($p['taller']) ?> (<?= e(fecha($p['fecha'])) ?>)</a><?php endforeach; ?>.
        </div>
    </div>
<?php endif; ?>

<div class="clases-lista mt" data-escalonar>
    <?php foreach ($hoy as $s): ?>
        <a class="clase-card lift" href="<?= e(url('/clases/ver', ['id' => $s['id']])) ?>" style="color:inherit">
            <span class="fecha-chip">Hoy · <?= hora($s['hora_inicio']) ?>–<?= hora($s['hora_fin']) ?></span>
            <strong style="font-size:17px"><?= e($s['taller']) ?></strong>
            <span class="soft"><?= (int) $s['ocupados'] ?> alumno(s) reservados de <?= (int) $s['cupo'] ?></span>
            <span><?= badge_estado_op($s['estado']) ?></span>
        </a>
    <?php endforeach; ?>
    <?php if (!$hoy): ?>
        <div class="card card-pad empty"><?= icono('agenda', 36) ?><div><strong>Hoy no tienes clases</strong></div></div>
    <?php endif; ?>
</div>

<div class="card mt anim">
    <div class="card-head"><h2>Próximos 7 días</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Día</th><th>Hora</th><th>Taller</th><th>Reservas</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($semana as $s): ?>
                <tr>
                    <td><a href="<?= e(url('/clases/ver', ['id' => $s['id']])) ?>"><?= e(fecha_larga($s['fecha'])) ?></a></td>
                    <td class="mono"><?= hora($s['hora_inicio']) ?>–<?= hora($s['hora_fin']) ?></td>
                    <td><?= e($s['taller']) ?></td>
                    <td><?= (int) $s['ocupados'] ?>/<?= (int) $s['cupo'] ?></td>
                    <td><?= badge_estado_op($s['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$semana): ?><div class="empty">Sin clases en los próximos días.</div><?php endif; ?>
    </div>
</div>
