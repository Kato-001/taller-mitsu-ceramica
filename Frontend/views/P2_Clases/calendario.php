<?php
/**
 * CU09 · Calendario semanal de clases (la maestra ve solo las suyas)
 * @var string $lunes @var array $sesiones @var array $talleres @var array $maestras @var ?int $taller @var ?int $maestra @var bool $propias
 */
$titulo = $propias ? 'Mis clases' : 'Calendario de clases';
$porDia = [];
foreach ($sesiones as $s) {
    $porDia[$s['fecha']][] = $s;
}
$anterior = date('Y-m-d', strtotime("$lunes -7 days"));
$siguiente = date('Y-m-d', strtotime("$lunes +7 days"));
$domingo = date('Y-m-d', strtotime("$lunes +6 days"));
$filtros = ['taller' => $taller, 'maestra' => $propias ? null : $maestra];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('agenda', 14) ?> Clases</div>
        <h1><?= $propias ? 'Mis clases' : 'Calendario de clases' ?></h1>
        <p>Semana del <?= e(fecha($lunes)) ?> al <?= e(fecha($domingo)) ?>. Toque una clase para ver sus alumnos<?= $propias ? ' y registrar la asistencia' : ', reservar o registrar asistencia' ?>.</p>
    </div>
    <?php if (Auth::puede('sesiones.gestionar')): ?>
        <form method="post" action="/clases/generar" class="inline" data-confirm="Generar clases del calendario" data-confirm-texto="Se crean las clases de las próximas semanas a partir de los horarios activos, sin duplicar las existentes." data-confirm-ok="Generar">
            <?= csrf_field() ?><button class="btn btn-outline"><?= icono('calendario-mas') ?> Generar calendario</button>
        </form>
    <?php endif; ?>
</div>

<form class="card toolbar anim" method="get" style="margin-bottom:16px">
    <div class="date-nav row">
        <a class="btn btn-outline btn-icon" href="<?= e(url('/clases', ['semana' => $anterior] + $filtros)) ?>" aria-label="Semana anterior"><?= icono('izquierda') ?></a>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/clases', $filtros)) ?>">Esta semana</a>
        <a class="btn btn-outline btn-icon" href="<?= e(url('/clases', ['semana' => $siguiente] + $filtros)) ?>" aria-label="Semana siguiente"><?= icono('derecha') ?></a>
    </div>
    <input type="hidden" name="semana" value="<?= e($lunes) ?>">
    <select class="select" name="taller" data-autoenviar aria-label="Taller">
        <option value="">Todos los talleres</option>
        <?php foreach ($talleres as $t): ?><option value="<?= $t['id'] ?>" <?= (int) $taller === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option><?php endforeach; ?>
    </select>
    <?php if (!$propias): ?>
        <select class="select" name="maestra" data-autoenviar aria-label="Maestra">
            <option value="">Todas las maestras</option>
            <?php foreach ($maestras as $m): ?><option value="<?= $m['id'] ?>" <?= (int) $maestra === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nombre_completo']) ?></option><?php endforeach; ?>
        </select>
    <?php endif; ?>
    <span class="faint" style="font-size:13px;margin-left:auto"><?= count($sesiones) ?> clase(s) en la semana</span>
</form>

<div class="semana" data-escalonar>
    <?php for ($i = 0; $i < 7; $i++): $dia = date('Y-m-d', strtotime("$lunes +$i days")); $lista = $porDia[$dia] ?? []; ?>
        <div class="dia-col<?= $dia === hoy() ? ' hoy' : '' ?>">
            <h3><span><?= mb_substr(DIAS_SEMANA[$i + 1], 0, 3) ?> <?= date('j', strtotime($dia)) ?></span><?php if ($dia === hoy()): ?><span style="color:var(--primary)">Hoy</span><?php endif; ?></h3>
            <?php foreach ($lista as $s):
                $pct = $s['cupo'] ? min(100, $s['ocupados'] / $s['cupo'] * 100) : 0;
                $clase = $s['estado'] === 'CANCELADA' ? 'cancelada' : ($s['publico'] === 'NINOS' ? 'ninos' : '');
                $clase .= $s['ocupados'] >= $s['cupo'] ? ' llena' : ''; ?>
                <a class="clase-chip <?= $clase ?>" href="<?= e(url('/clases/ver', ['id' => $s['id']])) ?>" title="<?= e($s['taller'] . ' · ' . $s['maestra']) ?>">
                    <strong><?= hora($s['hora_inicio']) ?> · <?= e($s['taller']) ?></strong>
                    <?= e($s['maestra']) ?> · <?= (int) $s['ocupados'] ?>/<?= (int) $s['cupo'] ?>
                    <?php if ($s['estado'] !== 'CANCELADA'): ?><div class="cupo-bar"><i style="width:<?= $pct ?>%"></i></div><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$lista): ?><span class="faint" style="font-size:12.5px">Sin clases</span><?php endif; ?>
        </div>
    <?php endfor; ?>
</div>
<p class="faint" style="font-size:13px;margin-top:14px">Colores: terracota = adultos · verde = niños · borde ámbar = clase llena · tachado = cancelada.</p>
