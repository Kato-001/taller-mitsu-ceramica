<?php
/**
 * Panel de administración y personal administrativo.
 * @var array $k @var array $dias @var array $clasesHoy @var array $alertasMat @var array $alertasPz
 * @var ?array $caja @var array $actividad @var array $top
 */
$titulo = 'Panel';
$u = Auth::usuario();
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$maxDia = max(1, ...array_map('floatval', array_column($dias, 'total')));
$tarjetas = [
    ['Clases hoy', (int) $k['clases_hoy'], 'agenda', '#a8552f', '/clases', ''],
    ['Alumnos esperados hoy', (int) $k['alumnos_hoy'], 'escuela', '#5f7f62', '/clases', ''],
    ['Ventas de hoy (Bs)', (float) $k['ventas_hoy'], 'carrito', '#c27c14', '/ventas?desde=' . hoy(), ''],
    ['Alertas de stock', (int) $k['materiales_bajos'] + (int) $k['piezas_bajas'], 'alerta', '#c0392b', '/materiales?alerta=1', ''],
];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('brillo', 14) ?> Panel del taller</div>
        <h1><?= e($saludo) ?>, <?= e($u['nombre']) ?></h1>
        <p>Así va el taller hoy, <?= e(fecha_larga(hoy())) ?>.</p>
    </div>
    <div class="row">
        <?php if (Auth::puede('ventas.registrar')): ?><a href="/ventas/nueva" class="btn btn-primary"><?= icono('carrito') ?> Vender</a><?php endif; ?>
        <?php if (Auth::puede('compras.gestionar')): ?><a href="/compras/nueva" class="btn btn-outline"><?= icono('camion') ?> Registrar compra</a><?php endif; ?>
    </div>
</div>

<div class="stats" data-escalonar>
    <?php foreach ($tarjetas as [$lbl, $val, $ico, $color, $link]): ?>
        <a href="<?= e($link) ?>" class="card stat lift" style="color:inherit">
            <div class="glow" style="background: <?= $color ?>"></div>
            <div class="ico" style="background: <?= $color ?>1a; color: <?= $color ?>"><?= icono($ico, 22) ?></div>
            <div class="val" data-contar="<?= is_float($val) ? number_format($val, 2, '.', '') : $val ?>">0</div>
            <div class="lbl"><?= e($lbl) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="split" data-escalonar>
    <div class="card">
        <div class="card-head">
            <h2>Ventas de los últimos 7 días</h2>
            <span class="faint" style="font-size:13px">Este mes: <strong><?= bs($k['ventas_mes']) ?></strong></span>
        </div>
        <div class="card-pad">
            <div class="bars" style="height:200px">
                <?php foreach ($dias as $i => $d): $esHoy = $d['dia'] === hoy(); ?>
                    <div class="bar-col<?= $esHoy ? ' hoy' : '' ?>" title="<?= e(fecha($d['dia'])) ?>: <?= bs($d['total']) ?>">
                        <b><?= (float) $d['total'] > 0 ? number_format((float) $d['total'], 0, ',', '.') : '0' ?></b>
                        <div class="bar grow" style="--i:<?= $i ?>;height:<?= max(3, round((float) $d['total'] / $maxDia * 100)) ?>%"></div>
                        <small><?= $esHoy ? 'Hoy' : mb_substr(DIAS_SEMANA[(int) date('N', strtotime($d['dia']))], 0, 2) . ' ' . date('j', strtotime($d['dia'])) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Caja</h2><?php if (Auth::puede('caja.gestionar')): ?><a href="/caja" class="btn btn-ghost btn-sm">Ir a caja <?= icono('flecha', 15) ?></a><?php endif; ?></div>
        <div class="card-pad">
            <?php if ($caja): ?>
                <p class="m0"><?= badge_estado_op('ABIERTA') ?> desde las <?= e(date('H:i', strtotime($caja['abierta_en']))) ?> · <?= e($caja['usuario']) ?></p>
                <div class="kv-list mt">
                    <div class="kv"><span>Monto inicial</span><strong><?= bs($caja['monto_inicial']) ?></strong></div>
                    <div class="kv"><span>Ventas en efectivo</span><strong><?= bs($caja['efectivo']) ?></strong></div>
                    <div class="kv"><span>QR y transferencias</span><strong><?= bs($caja['otros']) ?></strong></div>
                    <div class="kv"><span>Efectivo esperado</span><strong><?= bs((float) $caja['monto_inicial'] + (float) $caja['efectivo']) ?></strong></div>
                </div>
            <?php else: ?>
                <div class="empty" style="padding:20px 0"><?= icono('caja', 32) ?><div><strong>La caja está cerrada</strong></div><div class="faint">Ábrala para registrar ventas.</div></div>
            <?php endif; ?>
            <p class="faint m0 mt" style="font-size:13px">Asistencia de los últimos 30 días: <strong><?= $k['asistencia_30'] !== null ? (int) $k['asistencia_30'] . ' %' : '—' ?></strong> · Alumnos activos: <strong><?= (int) $k['alumnos'] ?></strong></p>
        </div>
    </div>
</div>

<div class="split mt" data-escalonar>
    <div class="card">
        <div class="card-head"><h2>Clases de hoy</h2><a href="/clases" class="btn btn-ghost btn-sm">Calendario <?= icono('flecha', 15) ?></a></div>
        <?php if (!$clasesHoy): ?>
            <div class="empty"><?= icono('agenda', 36) ?><div><strong>Hoy no hay clases</strong></div><div class="faint">El taller dicta clases de martes a sábado.</div></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Hora</th><th>Taller</th><th>Maestra</th><th>Cupo</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($clasesHoy as $s): ?>
                        <tr>
                            <td class="mono"><a href="<?= e(url('/clases/ver', ['id' => $s['id']])) ?>"><?= hora($s['hora_inicio']) ?>–<?= hora($s['hora_fin']) ?></a></td>
                            <td><strong><?= e($s['taller']) ?></strong></td>
                            <td><?= e($s['maestra']) ?></td>
                            <td><?= (int) $s['ocupados'] ?>/<?= (int) $s['cupo'] ?></td>
                            <td><?= badge_estado_op($s['estado']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Alertas de stock</h2><a href="/materiales?alerta=1" class="btn btn-ghost btn-sm">Ver todo</a></div>
        <div class="card-pad" style="padding-top:6px">
            <?php if (!$alertasMat && !$alertasPz): ?>
                <div class="empty" style="padding:20px 0"><?= icono('ok-circulo', 32) ?><div><strong>Todo el inventario está sobre el mínimo</strong></div></div>
            <?php endif; ?>
            <?php foreach ($alertasMat as $m): $pct = (float) $m['stock_minimo'] > 0 ? min(100, (float) $m['stock'] / (float) $m['stock_minimo'] * 100) : 0; ?>
                <div class="alerta-linea">
                    <span><a href="<?= e(url('/materiales/kardex', ['id' => $m['id']])) ?>"><?= e($m['nombre']) ?></a><br><small class="faint">Material · mínimo <?= cantidad($m['stock_minimo']) ?> <?= e($m['unidad']) ?></small></span>
                    <span style="text-align:right"><strong><?= cantidad($m['stock']) ?> <?= e($m['unidad']) ?></strong><div class="medidor"><i class="<?= $pct <= 0 ? 'cero' : '' ?>" style="width:<?= max(4, $pct) ?>%"></i></div></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($alertasPz as $p): ?>
                <div class="alerta-linea">
                    <span><a href="<?= e(url('/piezas/kardex', ['id' => $p['id']])) ?>"><?= e($p['nombre']) ?></a><br><small class="faint">Pieza <?= e($p['codigo']) ?> · mínimo <?= (int) $p['stock_minimo'] ?></small></span>
                    <span><?= badge_stock((float) $p['stock'], (float) $p['stock_minimo']) ?> <strong><?= (int) $p['stock'] ?> u.</strong></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="split mt" data-escalonar>
    <div class="card">
        <div class="card-head"><h2>Piezas más vendidas</h2><span class="faint" style="font-size:13px">Últimos 30 días</span></div>
        <div class="card-pad" style="padding-top:8px;padding-bottom:12px">
            <?php $maxTop = max(1, ...array_map('intval', array_column($top, 'unidades') ?: [0])); ?>
            <?php foreach ($top as $i => $t): ?>
                <div class="hbar-row">
                    <span><?= e($t['nombre']) ?></span>
                    <div class="hbar-track"><div class="hbar grow-x" style="--i:<?= $i ?>;width:<?= round($t['unidades'] / $maxTop * 100) ?>%"></div></div>
                    <b><?= (int) $t['unidades'] ?></b>
                </div>
            <?php endforeach; ?>
            <?php if (!$top): ?><div class="empty">Sin ventas en los últimos 30 días.</div><?php endif; ?>
        </div>
    </div>
    <?php if ($actividad): ?>
    <div class="card">
        <div class="card-head"><h2>Actividad reciente</h2><a href="/bitacora" class="btn btn-ghost btn-sm">Bitácora <?= icono('flecha', 15) ?></a></div>
        <?php foreach ($actividad as $a): ?>
            <div class="feed-item">
                <div class="feed-ico" style="color:var(--accent);background:var(--accent-soft)"><?= icono('actividad', 17) ?></div>
                <div style="flex:1;min-width:0">
                    <p><strong><?= e($a['usuario'] ?? 'Sistema') ?></strong> · <?= e($a['detalle']) ?></p>
                    <small><?= e(str_replace('_', ' ', mb_strtolower($a['accion']))) ?> · <?= e(hace_cuanto($a['fecha'])) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
