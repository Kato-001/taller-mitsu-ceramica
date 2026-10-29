<?php
/**
 * CU10 · El alumno reserva una clase con cupo
 * @var array $sesiones @var array $talleres @var ?int $taller
 */
$titulo = 'Reservar clase';
$porDia = [];
foreach ($sesiones as $s) {
    $porDia[$s['fecha']][] = $s;
}
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><a href="/portal"><?= icono('izquierda', 14) ?> Mi espacio</a></div>
        <h1>Reservar una clase</h1>
        <p>Clases de las próximas 4 semanas con su cupo en tiempo real.</p>
    </div>
    <form method="get" class="inline">
        <select class="select" name="taller" data-autoenviar aria-label="Filtrar por taller">
            <option value="">Todos los talleres</option>
            <?php foreach ($talleres as $t): ?><option value="<?= $t['id'] ?>" <?= (int) $taller === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<?php foreach ($porDia as $dia => $lista): ?>
    <h2 style="font-size:16px;margin:22px 0 10px;text-transform:capitalize"><?= e(fecha_larga($dia)) ?></h2>
    <div class="clases-lista" data-escalonar>
        <?php foreach ($lista as $s): $libres = (int) $s['cupo'] - (int) $s['ocupados']; ?>
            <div class="clase-card">
                <span class="fecha-chip"><?= hora($s['hora_inicio']) ?>–<?= hora($s['hora_fin']) ?> · <?= e(PUBLICOS[$s['publico']] ?? '') ?></span>
                <strong style="font-size:17px;font-family:var(--serif)"><?= e($s['taller']) ?></strong>
                <span class="soft">Con <?= e($s['maestra']) ?> · <?= e(NIVELES[$s['nivel']] ?? '') ?></span>
                <div>
                    <div class="row-between" style="font-size:13px"><span><?= $libres > 0 ? "$libres cupo(s) libre(s)" : 'Clase llena' ?></span><span class="faint"><?= (int) $s['ocupados'] ?>/<?= (int) $s['cupo'] ?></span></div>
                    <div class="cupo-bar"><i style="width:<?= min(100, $s['ocupados'] / max(1, $s['cupo']) * 100) ?>%"></i></div>
                </div>
                <?php if ($libres > 0): ?>
                    <form method="post" action="/portal/reservar" data-confirm="Confirmar reserva" data-confirm-texto="<?= e($s['taller'] . ' · ' . fecha_larga($s['fecha']) . ' a las ' . hora($s['hora_inicio'])) ?>" data-confirm-ok="Reservar">
                        <?= csrf_field() ?><input type="hidden" name="sesion_id" value="<?= $s['id'] ?>">
                        <button class="btn btn-primary btn-block btn-sm"><?= icono('check', 15) ?> Reservar mi lugar</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-outline btn-block btn-sm" disabled>Sin cupo</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php if (!$sesiones): ?>
    <div class="card card-pad empty"><?= icono('calendario', 36) ?><div><strong>No hay clases disponibles</strong></div><div class="faint">Prueba con otro taller o vuelve más tarde.</div></div>
<?php endif; ?>
