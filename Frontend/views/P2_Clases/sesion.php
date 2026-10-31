<?php
/**
 * Detalle de una clase · CU10 reservar (recepción) · CU11 registrar asistencia
 * @var array $sesion @var array $reservas @var array $alumnos @var array $maestras
 */
$s = $sesion;
$titulo = $s['taller'];
$activas = array_filter($reservas, fn($r) => $r['estado'] !== 'CANCELADA');
$libres = (int) $s['cupo'] - count($activas);
$puedeAsistencia = $s['estado'] !== 'CANCELADA' && $s['fecha'] <= hoy() && $activas;
$futura = $s['estado'] === 'PROGRAMADA' && ($s['fecha'] > hoy() || ($s['fecha'] === hoy() && $s['hora_inicio'] > date('H:i:s')));
$reservadosIds = array_column($activas, 'alumno_id');
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="<?= e(url('/clases', ['semana' => $s['fecha']])) ?>"><?= icono('izquierda', 14) ?> Calendario</a></div>
        <h1><?= e($s['taller']) ?></h1>
        <p><?= e(fecha_larga($s['fecha'])) ?> · <?= hora($s['hora_inicio']) ?> a <?= hora($s['hora_fin']) ?> · <?= e($s['maestra']) ?> · <?= badge_estado_op($s['estado']) ?></p>
    </div>
    <?php if (Auth::puede('sesiones.gestionar') && $s['estado'] === 'PROGRAMADA'): ?>
        <form method="post" action="/clases/cancelar" class="inline" data-confirm="Cancelar esta clase" data-confirm-texto="Se cancelarán también las <?= count($activas) ?> reserva(s) pendientes." data-motivo="Motivo de la cancelación" data-confirm-ok="Cancelar clase" data-peligro>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button class="btn btn-ghost btn-danger-ghost"><?= icono('x-circulo') ?> Cancelar clase</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($s['estado'] === 'CANCELADA'): ?>
    <div class="alert alert-error anim"><?= icono('alerta') ?><div>Clase cancelada: <?= e($s['motivo_cancelacion']) ?></div></div>
<?php endif; ?>

<div class="stats" data-escalonar>
    <div class="card stat"><div class="ico" style="background:var(--primary-soft);color:var(--primary)"><?= icono('escuela', 22) ?></div><div class="val"><?= count($activas) ?></div><div class="lbl">Reservas activas</div></div>
    <div class="card stat"><div class="ico" style="background:var(--accent-soft);color:var(--accent)"><?= icono('usuario-mas', 22) ?></div><div class="val"><?= max(0, $libres) ?></div><div class="lbl">Cupos libres de <?= (int) $s['cupo'] ?></div></div>
    <div class="card stat"><div class="ico" style="background:var(--ok-soft);color:var(--ok)"><?= icono('check', 22) ?></div><div class="val"><?= count(array_filter($reservas, fn($r) => $r['estado'] === 'ASISTIO')) ?></div><div class="lbl">Asistieron</div></div>
    <div class="card stat"><div class="ico" style="background:var(--warn-soft);color:var(--warn)"><?= icono('x-circulo', 22) ?></div><div class="val"><?= count(array_filter($reservas, fn($r) => $r['estado'] === 'FALTO')) ?></div><div class="lbl">Faltaron</div></div>
</div>

<div class="split" data-escalonar>
    <form method="post" action="/clases/asistencia" class="card">
        <?= csrf_field() ?><input type="hidden" name="sesion_id" value="<?= $s['id'] ?>">
        <div class="card-head">
            <h2>Alumnos y asistencia</h2>
            <?php if ($puedeAsistencia): ?><span class="faint" style="font-size:13px">Marque y guarde</span><?php elseif ($s['fecha'] > hoy()): ?><span class="faint" style="font-size:13px">La asistencia se marca el día de la clase</span><?php endif; ?>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Alumno</th><th>Nivel</th><th>Estado</th><?php if ($puedeAsistencia): ?><th>Asistencia</th><?php endif; ?><?php if (Auth::puede('reservas.gestionar') && $futura): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($reservas as $r): $cancelada = $r['estado'] === 'CANCELADA'; ?>
                    <tr <?= $cancelada ? 'style="opacity:.55"' : '' ?>>
                        <td><strong><?= e($r['alumno']) ?></strong><?= $r['telefono'] ? '<div class="faint" style="font-size:12px">' . e($r['telefono']) . '</div>' : '' ?></td>
                        <td><?= e(NIVELES[$r['nivel']] ?? '') ?></td>
                        <td><?= badge_estado_op($r['estado']) ?><?= $cancelada && $r['motivo_cancelacion'] ? '<div class="faint" style="font-size:12px">' . e($r['motivo_cancelacion']) . '</div>' : '' ?></td>
                        <?php if ($puedeAsistencia): ?>
                            <td><?php if (!$cancelada): ?>
                                <div class="chips" role="radiogroup" aria-label="Asistencia de <?= e($r['alumno']) ?>">
                                    <label class="chip-check"><input type="radio" name="asistencia[<?= $r['id'] ?>]" value="ASISTIO" <?= $r['estado'] === 'ASISTIO' ? 'checked' : '' ?>><span>Presente</span></label>
                                    <label class="chip-check"><input type="radio" name="asistencia[<?= $r['id'] ?>]" value="FALTO" <?= $r['estado'] === 'FALTO' ? 'checked' : '' ?>><span>Falta</span></label>
                                </div>
                            <?php endif; ?></td>
                        <?php endif; ?>
                        <?php if (Auth::puede('reservas.gestionar') && $futura): ?>
                            <td><?php if ($r['estado'] === 'RESERVADA'): ?>
                                <button type="submit" form="cancelar-<?= $r['id'] ?>" class="btn btn-ghost btn-icon btn-sm btn-danger-ghost" title="Cancelar reserva" data-sin-carga="1"><?= icono('cerrar', 15) ?></button>
                            <?php endif; ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!$reservas): ?><div class="empty"><?= icono('escuela', 32) ?><div><strong>Aún no hay reservas para esta clase</strong></div></div><?php endif; ?>
        </div>
        <?php if ($puedeAsistencia): ?>
            <div class="card-pad row" style="justify-content:space-between;border-top:1px solid var(--border)">
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.querySelectorAll('input[value=ASISTIO]').forEach(function(i){i.checked=true})"><?= icono('check', 15) ?> Todos presentes</button>
                <button class="btn btn-primary"><?= icono('check') ?> Guardar asistencia</button>
            </div>
        <?php endif; ?>
    </form>
    <?php foreach ($reservas as $r): if ($r['estado'] === 'RESERVADA' && $futura): ?>
        <form method="post" action="/reservas/cancelar" id="cancelar-<?= $r['id'] ?>" hidden data-confirm="Cancelar la reserva de <?= e($r['alumno']) ?>" data-motivo="Motivo" data-confirm-ok="Cancelar reserva" data-peligro>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
        </form>
    <?php endif; endforeach; ?>

    <div class="stack">
        <?php if (Auth::puede('reservas.gestionar') && $futura): ?>
            <form method="post" action="/clases/reservar" class="card card-pad stack" data-validar>
                <?= csrf_field() ?><input type="hidden" name="sesion_id" value="<?= $s['id'] ?>">
                <h2 style="font-size:17px"><?= icono('calendario-mas', 18) ?> Reservar para un alumno</h2>
                <?php if ($libres > 0): ?>
                    <label class="field"><span>Alumno</span>
                        <select class="select" name="alumno_id" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($alumnos as $a): if (in_array($a['id'], $reservadosIds)) continue; ?>
                                <option value="<?= $a['id'] ?>"><?= e($a['nombre_completo'] . ($a['ci'] ? ' · CI ' . $a['ci'] : '')) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <button class="btn btn-primary"><?= icono('check') ?> Reservar cupo</button>
                <?php else: ?>
                    <p class="soft m0">La clase está llena.</p>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <?php if (Auth::puede('sesiones.gestionar') && $s['estado'] === 'PROGRAMADA'): ?>
            <form method="post" action="/clases/maestra" class="card card-pad stack">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                <h2 style="font-size:17px"><?= icono('usuario-ok', 18) ?> Maestra a cargo</h2>
                <p class="soft m0" style="font-size:13.5px">Si la maestra no puede venir, asigne un reemplazo solo para esta clase.</p>
                <div class="row">
                    <select class="select" name="maestra_id" required style="flex:1" aria-label="Maestra">
                        <?php foreach ($maestras as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $m['id'] === (int) $s['maestra_id'] ? 'selected' : '' ?>><?= e($m['nombre_completo']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline">Cambiar</button>
                </div>
            </form>
        <?php endif; ?>
        <div class="card card-pad">
            <div class="kv-list">
                <div class="kv"><span>Taller</span><strong><?= e($s['taller']) ?></strong></div>
                <div class="kv"><span>Público</span><strong><?= e(PUBLICOS[$s['publico']] ?? '') ?></strong></div>
                <div class="kv"><span>Nivel</span><strong><?= e(NIVELES[$s['nivel']] ?? '') ?></strong></div>
                <div class="kv"><span>Cupo</span><strong><?= (int) $s['cupo'] ?> alumnos</strong></div>
            </div>
        </div>
    </div>
</div>
