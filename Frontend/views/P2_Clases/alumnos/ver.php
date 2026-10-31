<?php
/**
 * CU12 · Ficha del alumno · CU10 · Reservar clase para el alumno (recepción)
 * @var array $alumno @var array $proximas @var array $historial @var array $disponibles
 */
$titulo = $alumno['nombre_completo'];
$tot = (int) $alumno['asistencias'] + (int) $alumno['faltas'];
$act = $alumno['estado'] === 'ACTIVO';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/alumnos"><?= icono('izquierda', 14) ?> Alumnos</a></div>
        <h1><?= e($alumno['nombre_completo']) ?></h1>
        <p><?= badge(NIVELES[$alumno['nivel']] ?? '', 'badge-info', false) ?> <?= badge_estado($alumno['estado']) ?> · Alumno desde <?= e(fecha($alumno['creado_en'])) ?></p>
    </div>
    <div class="row">
        <a class="btn btn-outline" href="<?= e(url('/alumnos/editar', ['id' => $alumno['id']])) ?>"><?= icono('editar') ?> Editar</a>
        <form method="post" action="/alumnos/estado" class="inline" data-confirm="<?= $act ? 'Desactivar alumno' : 'Activar alumno' ?>"
              data-confirm-texto="<?= $act ? 'No podrá ingresar ni reservar. Su historial se conserva.' : 'Podrá volver a reservar.' ?>" data-confirm-ok="<?= $act ? 'Desactivar' : 'Activar' ?>" <?= $act ? 'data-peligro' : '' ?>>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $alumno['id'] ?>">
            <button class="btn btn-ghost <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" data-sin-carga="1"><?= icono('power') ?> <?= $act ? 'Desactivar' : 'Activar' ?></button>
        </form>
    </div>
</div>

<div class="split" data-escalonar>
    <div class="card card-pad">
        <div class="kv-list">
            <div class="kv"><span>Correo</span><strong><?= e($alumno['correo']) ?></strong></div>
            <div class="kv"><span>Teléfono</span><strong><?= e($alumno['telefono'] ?: '—') ?></strong></div>
            <div class="kv"><span>CI</span><strong class="mono"><?= e($alumno['ci'] ?: '—') ?></strong></div>
            <div class="kv"><span>Edad</span><strong><?= e(edad($alumno['fecha_nacimiento'])) ?></strong></div>
            <div class="kv"><span>Asistencia</span><strong><?= $tot ? round($alumno['asistencias'] / $tot * 100) . ' % (' . (int) $alumno['asistencias'] . ' de ' . $tot . ')' : 'Sin clases aún' ?></strong></div>
            <div class="kv"><span>Último ingreso al portal</span><strong><?= e(hace_cuanto($alumno['ultimo_acceso'])) ?></strong></div>
        </div>
    </div>
    <?php if ($act && Auth::puede('reservas.gestionar')): ?>
    <form method="post" action="/clases/reservar" class="card card-pad stack" data-validar>
        <?= csrf_field() ?><input type="hidden" name="alumno_id" value="<?= $alumno['id'] ?>">
        <h2 style="font-size:17px"><?= icono('calendario-mas', 18) ?> Reservar una clase</h2>
        <label class="field"><span>Clase con cupo (próximas 3 semanas)</span>
            <select class="select" name="sesion_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($disponibles as $s): $libres = (int) $s['cupo'] - (int) $s['ocupados']; ?>
                    <option value="<?= $s['id'] ?>" <?= $libres <= 0 ? 'disabled' : '' ?>><?= e(fecha_larga($s['fecha']) . ' · ' . hora($s['hora_inicio']) . ' · ' . $s['taller'] . ' · ' . ($libres > 0 ? "$libres cupo(s)" : 'lleno')) ?></option>
                <?php endforeach; ?>
            </select></label>
        <button class="btn btn-primary"><?= icono('check') ?> Reservar</button>
    </form>
    <?php endif; ?>
</div>

<div class="card mt anim">
    <div class="card-head"><h2>Próximas clases</h2><span class="faint" style="font-size:13px"><?= count($proximas) ?></span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Hora</th><th>Taller</th><th>Maestra</th><th style="text-align:right">Acción</th></tr></thead>
            <tbody>
            <?php foreach ($proximas as $r): ?>
                <tr>
                    <td><a href="<?= e(url('/clases/ver', ['id' => $r['sesion_id']])) ?>"><?= e(fecha_larga($r['fecha'])) ?></a></td>
                    <td class="mono"><?= hora($r['hora_inicio']) ?>–<?= hora($r['hora_fin']) ?></td>
                    <td><?= e($r['taller']) ?></td>
                    <td><?= e($r['maestra']) ?></td>
                    <td><div class="actions">
                        <form method="post" action="/reservas/cancelar" class="inline" data-confirm="Cancelar la reserva" data-confirm-texto="Se liberará el cupo." data-motivo="Motivo de la cancelación" data-confirm-ok="Cancelar reserva" data-peligro>
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-ghost btn-sm btn-danger-ghost" data-sin-carga="1"><?= icono('x-circulo', 15) ?> Cancelar</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$proximas): ?><div class="empty">No tiene clases reservadas.</div><?php endif; ?>
    </div>
</div>

<div class="card mt anim">
    <div class="card-head"><h2>Historial</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Fecha</th><th>Taller</th><th>Maestra</th><th>Resultado</th></tr></thead>
            <tbody>
            <?php foreach ($historial as $r): ?>
                <tr>
                    <td><?= e(fecha($r['fecha'])) ?> <span class="faint mono"><?= hora($r['hora_inicio']) ?></span></td>
                    <td><?= e($r['taller']) ?></td>
                    <td><?= e($r['maestra']) ?></td>
                    <td><?= badge_estado_op($r['estado']) ?><?= $r['estado'] === 'CANCELADA' && $r['motivo_cancelacion'] ? '<div class="faint" style="font-size:12px">' . e($r['motivo_cancelacion']) . '</div>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$historial): ?><div class="empty">Aún no tiene historial.</div><?php endif; ?>
    </div>
</div>
