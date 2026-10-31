<?php
/**
 * CU09 · Horarios semanales de los talleres
 * @var array $horarios @var array $talleres @var array $maestras @var ?array $editar
 */
$titulo = 'Horarios';
$h = $editar;
$porDia = [];
foreach ($horarios as $x) {
    $porDia[(int) $x['dia_semana']][] = $x;
}
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('reloj', 14) ?> Clases</div>
        <h1>Horarios semanales</h1>
        <p>Cada horario se repite todas las semanas. Una maestra no puede tener dos horarios que se crucen el mismo día (lo impide la base de datos).</p>
    </div>
    <form method="post" action="/clases/generar" class="inline" data-confirm="Generar clases del calendario" data-confirm-texto="Se crean las clases de las próximas <?= (int) config('SEMANAS_CALENDARIO', '4') ?> semanas a partir de los horarios activos. Las que ya existen no se duplican." data-confirm-ok="Generar">
        <?= csrf_field() ?>
        <button class="btn btn-primary"><?= icono('calendario-mas') ?> Generar calendario</button>
    </form>
</div>

<div class="roles-layout">
    <form method="post" action="/horarios/guardar" class="card card-pad anim stack" data-validar>
        <?= csrf_field() ?>
        <?php if ($h): ?><input type="hidden" name="id" value="<?= $h['id'] ?>"><?php endif; ?>
        <h2 style="font-size:17px"><?= $h ? 'Editar horario' : 'Programar horario' ?></h2>
        <label class="field"><span>Taller *</span>
            <select class="select<?= clase_error('taller_id') ?>" name="taller_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($talleres as $t): ?><option value="<?= $t['id'] ?>" <?= (string) old('taller_id', $h['taller_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option><?php endforeach; ?>
            </select><?= error_campo('taller_id') ?></label>
        <label class="field"><span>Maestra *</span>
            <select class="select<?= clase_error('maestra_id') ?>" name="maestra_id" required>
                <option value="">Seleccione…</option>
                <?php foreach ($maestras as $m): ?><option value="<?= $m['id'] ?>" <?= (string) old('maestra_id', $h['maestra_id'] ?? '') === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['nombre_completo'] . ' · ' . $m['especialidad']) ?></option><?php endforeach; ?>
            </select><?= error_campo('maestra_id') ?></label>
        <div class="grid-2">
            <label class="field"><span>Día *</span>
                <select class="select" name="dia_semana" required>
                    <?php foreach (DIAS_SEMANA as $n => $d): ?><option value="<?= $n ?>" <?= (string) old('dia_semana', $h['dia_semana'] ?? '2') === (string) $n ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?>
                </select></label>
            <label class="field"><span>Cupo *</span>
                <input class="input<?= clase_error('cupo') ?>" type="number" name="cupo" value="<?= e(old('cupo', $h['cupo'] ?? '8')) ?>" required min="1" max="30"><?= error_campo('cupo') ?></label>
            <label class="field"><span>Inicio *</span>
                <input class="input<?= clase_error('hora_inicio') ?>" type="time" name="hora_inicio" value="<?= e(old('hora_inicio', substr($h['hora_inicio'] ?? '10:00', 0, 5))) ?>" required min="07:00" max="22:00"><?= error_campo('hora_inicio') ?></label>
            <label class="field"><span>Fin *</span>
                <input class="input<?= clase_error('hora_fin') ?>" type="time" name="hora_fin" value="<?= e(old('hora_fin', substr($h['hora_fin'] ?? '12:00', 0, 5))) ?>" required min="07:00" max="22:00"><?= error_campo('hora_fin') ?></label>
        </div>
        <div class="row" style="justify-content:flex-end">
            <?php if ($h): ?><a href="/horarios" class="btn btn-ghost">Cancelar</a><?php endif; ?>
            <button class="btn btn-primary"><?= icono('check') ?> <?= $h ? 'Guardar' : 'Programar' ?></button>
        </div>
    </form>

    <div class="stack" data-escalonar>
        <?php foreach (DIAS_SEMANA as $n => $dia): if (empty($porDia[$n])) continue; ?>
            <div class="card">
                <div class="card-head"><h2><?= $dia ?></h2><span class="faint" style="font-size:13px"><?= count($porDia[$n]) ?> horario(s)</span></div>
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                        <?php foreach ($porDia[$n] as $x): $act = $x['estado'] === 'ACTIVO'; ?>
                            <tr <?= $act ? '' : 'style="opacity:.55"' ?>>
                                <td class="mono" style="width:120px"><?= hora($x['hora_inicio']) ?>–<?= hora($x['hora_fin']) ?></td>
                                <td><strong><?= e($x['taller']) ?></strong><div class="faint" style="font-size:12.5px"><?= e($x['maestra']) ?> · cupo <?= (int) $x['cupo'] ?></div></td>
                                <td><?= badge_estado($x['estado']) ?></td>
                                <td><div class="actions">
                                    <a class="btn btn-ghost btn-icon btn-sm" href="<?= e(url('/horarios', ['editar' => $x['id']])) ?>" title="Editar"><?= icono('editar', 16) ?></a>
                                    <form method="post" action="/horarios/estado" class="inline">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
                                        <button class="btn btn-ghost btn-icon btn-sm <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" title="<?= $act ? 'Desactivar' : 'Activar' ?>" data-sin-carga="1"><?= icono('power', 16) ?></button>
                                    </form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
