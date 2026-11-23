<?php
/**
 * CU26 · Calificaciones recibidas (taller): promedio, filtros y respuesta
 * @var array $resumen @var array $lista @var array $f @var Paginador $pag
 */
$titulo = 'Calificaciones';
$n = fn($v) => number_format((float) $v, 1, ',', '.');
?>
<div class="page-head"><div>
    <div class="eyebrow"><?= icono('estrella', 14) ?> Reportes</div>
    <h1>Calificaciones del servicio</h1>
    <p>Lo que opinan los clientes y alumnos de sus compras y clases.</p>
</div></div>

<div class="stats" data-escalonar>
    <?php foreach ([['Promedio general', $resumen['total'] ? $n($resumen['promedio']) . ' / 5' : '—'], ['Compras en la tienda', (float) $resumen['pedidos'] ? $n($resumen['pedidos']) . ' / 5' : '—'],
                    ['Clases', (float) $resumen['clases'] ? $n($resumen['clases']) . ' / 5' : '—'], ['Sin responder', $resumen['sin_respuesta'] . ' de ' . $resumen['total']]] as [$lbl, $val]): ?>
        <div class="card stat"><div><small class="soft"><?= e($lbl) ?></small><div style="font-family:var(--serif);font-size:26px;font-weight:600"><?= e($val) ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card anim mt">
    <form class="toolbar" method="get">
        <select class="select" name="tipo" data-autoenviar aria-label="Servicio">
            <option value="">Todos los servicios</option>
            <?php foreach (Calificacion::TIPOS as $k => $t): ?><option value="<?= $k ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="puntaje" data-autoenviar aria-label="Puntaje">
            <option value="">Todos los puntajes</option>
            <?php for ($i = 5; $i; $i--): ?><option value="<?= $i ?>" <?= $f['puntaje'] === (string) $i ? 'selected' : '' ?>><?= $i ?> estrella<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
        </select>
        <select class="select" name="respuesta" data-autoenviar aria-label="Respuesta">
            <option value="">Con y sin respuesta</option>
            <option value="NO" <?= $f['respuesta'] === 'NO' ? 'selected' : '' ?>>Sin responder</option>
        </select>
    </form>
    <div class="card-pad stack">
        <?php foreach ($lista as $k): ?>
            <article class="calificacion">
                <div class="row-between"><div><strong><?= e($k['persona']) ?></strong> <?= Calificacion::estrellas((int) $k['puntaje']) ?></div><small class="faint"><?= e(fecha_hora($k['creado_en'])) ?></small></div>
                <small class="soft"><?= $k['tipo'] === 'PEDIDO' ? 'Pedido ' . e($k['numero']) : 'Clase de ' . e($k['taller']) . ' · ' . e(fecha($k['fecha_clase'])) ?></small>
                <p><?= $k['comentario'] ? e($k['comentario']) : '<span class="faint">Sin comentario</span>' ?></p>
                <?php if ($k['respuesta']): ?>
                    <div class="alert alert-ok"><?= icono('check', 16) ?><div><strong>Respuesta del taller:</strong> <?= e($k['respuesta']) ?></div></div>
                <?php else: ?>
                    <form method="post" action="/calificaciones/responder" class="row" style="flex-wrap:wrap">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $k['id'] ?>">
                        <input class="input" name="respuesta" required minlength="3" maxlength="400" placeholder="Escriba una respuesta" aria-label="Respuesta" style="flex:1;min-width:220px">
                        <button class="btn btn-outline btn-sm">Responder</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$lista): ?><div class="empty"><?= icono('estrella', 36) ?><div><strong>No hay calificaciones con esos filtros</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>
