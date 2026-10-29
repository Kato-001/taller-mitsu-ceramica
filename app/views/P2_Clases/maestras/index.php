<?php
/**
 * CU07 · Maestras
 * @var array $maestras
 */
$titulo = 'Maestras';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('paleta', 14) ?> Clases</div>
        <h1>Maestras del taller</h1>
        <p>Cada maestra tiene su cuenta para ver sus clases y registrar la asistencia.</p>
    </div>
    <a href="/maestras/nueva" class="btn btn-primary"><?= icono('mas') ?> Nueva maestra</a>
</div>

<div class="grid-2" data-escalonar>
    <?php foreach ($maestras as $m): $act = $m['estado'] === 'ACTIVO'; ?>
        <div class="card card-pad" <?= $act ? '' : 'style="opacity:.6"' ?>>
            <div class="row-between">
                <div class="user-cell">
                    <div class="avatar" style="width:48px;height:48px;font-size:17px"><?= e(iniciales($m['nombre_completo'])) ?></div>
                    <div><strong style="font-size:16px"><?= e($m['nombre_completo']) ?></strong><small><?= e($m['especialidad']) ?></small></div>
                </div>
                <?= badge_estado($m['estado']) ?>
            </div>
            <?php if ($m['biografia']): ?><p class="soft" style="font-size:14px;margin:12px 0 0"><?= e($m['biografia']) ?></p><?php endif; ?>
            <div class="kv-list mt">
                <div class="kv"><span>Correo</span><strong><?= e($m['correo']) ?></strong></div>
                <div class="kv"><span>Teléfono</span><strong><?= e($m['telefono'] ?: '—') ?></strong></div>
                <div class="kv"><span>Horarios activos</span><strong><?= (int) $m['horarios'] ?></strong></div>
            </div>
            <div class="row mt" style="justify-content:flex-end">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('/clases', ['maestra' => $m['id']])) ?>"><?= icono('agenda', 15) ?> Clases</a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/maestras/editar', ['id' => $m['id']])) ?>"><?= icono('editar', 15) ?> Editar</a>
                <form method="post" action="/maestras/estado" class="inline" data-confirm="<?= $act ? 'Desactivar' : 'Activar' ?> a <?= e($m['nombre_completo']) ?>"
                      data-confirm-texto="<?= $act ? 'También se desactiva su cuenta. Si tiene horarios activos, primero hay que reasignarlos.' : 'Podrá volver a ingresar al sistema.' ?>"
                      data-confirm-ok="<?= $act ? 'Desactivar' : 'Activar' ?>" <?= $act ? 'data-peligro' : '' ?>>
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button class="btn btn-ghost btn-sm <?= $act ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" data-sin-carga="1"><?= icono('power', 15) ?> <?= $act ? 'Desactivar' : 'Activar' ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$maestras): ?><div class="card empty"><?= icono('paleta', 36) ?><div><strong>Aún no hay maestras registradas</strong></div></div><?php endif; ?>
