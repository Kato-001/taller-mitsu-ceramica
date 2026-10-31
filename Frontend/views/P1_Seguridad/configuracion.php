<?php
/**
 * CU06 · Configuración del taller y catálogos base
 * @var array $parametros @var array $categorias @var array $unidades @var array $metodos
 */
$titulo = 'Configuración';
$valores = $GLOBALS['_old']['valor'] ?? [];
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('ajustes', 14) ?> Administración</div>
        <h1>Configuración</h1>
        <p>Datos del taller, reglas de reservas y catálogos que usan los formularios.</p>
    </div>
</div>

<form method="post" action="/configuracion" class="card card-pad anim" style="max-width:900px" data-validar>
    <?= csrf_field() ?>
    <div class="section-title">Datos del taller y reglas</div>
    <div class="grid-2">
        <?php foreach ($parametros as $p): $num = isset(Configuracion::REGLAS[$p['clave']]); ?>
            <label class="field">
                <span><?= e($p['descripcion']) ?></span>
                <input class="input<?= clase_error($p['clave']) ?>" name="valor[<?= e($p['clave']) ?>]" value="<?= e($valores[$p['clave']] ?? $p['valor']) ?>" required maxlength="300"
                       <?= $num ? 'type="number" min="' . Configuracion::REGLAS[$p['clave']][0] . '" max="' . Configuracion::REGLAS[$p['clave']][1] . '"' : ($p['clave'] === 'CORREO' ? 'type="email"' : '') ?>>
                <small class="mono"><?= e($p['clave']) ?></small>
                <?= error_campo($p['clave']) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="row mt" style="justify-content:flex-end"><button class="btn btn-primary"><?= icono('check') ?> Guardar configuración</button></div>
</form>

<h2 id="catalogos" class="mt" style="font-size:20px;margin:28px 0 14px">Catálogos</h2>
<div class="grid-3" data-escalonar>
    <?php
    $bloques = [
        'categoria'   => ['Categorías', $categorias],
        'unidad'      => ['Unidades de medida', $unidades],
        'metodo_pago' => ['Métodos de pago', $metodos],
    ];
    foreach ($bloques as $tabla => [$nombre, $filas]): ?>
        <div class="card">
            <div class="card-head"><h2><?= e($nombre) ?></h2><span class="faint" style="font-size:13px"><?= count($filas) ?></span></div>
            <div class="card-pad" style="padding-top:4px">
                <?php foreach ($filas as $f): ?>
                    <div class="alerta-linea" <?= $f['estado'] !== 'ACTIVO' ? 'style="opacity:.55"' : '' ?>>
                        <form method="post" action="/catalogos/guardar" class="inline-edit" style="flex:1">
                            <?= csrf_field() ?><input type="hidden" name="tabla" value="<?= $tabla ?>"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <input class="input" name="nombre" value="<?= e($f['nombre']) ?>" required maxlength="60" aria-label="Nombre">
                            <?php if ($tabla === 'unidad'): ?><input class="input" name="abreviatura" value="<?= e($f['abreviatura']) ?>" required maxlength="10" style="max-width:70px" aria-label="Abreviatura"><?php endif; ?>
                            <button class="btn btn-ghost btn-icon btn-sm" title="Guardar" data-sin-carga="1"><?= icono('check', 16) ?></button>
                        </form>
                        <?php if ($tabla === 'categoria'): ?><small class="faint"><?= $f['tipo'] === 'PIEZA' ? 'Pieza' : 'Material' ?></small><?php endif; ?>
                        <form method="post" action="/catalogos/estado" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="tabla" value="<?= $tabla ?>"><input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button class="btn btn-ghost btn-icon btn-sm <?= $f['estado'] === 'ACTIVO' ? 'btn-danger-ghost' : 'btn-ok-ghost' ?>" title="<?= $f['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?> (<?= (int) $f['usos'] ?> uso(s))" data-sin-carga="1"><?= icono('power', 15) ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <form method="post" action="/catalogos/guardar" class="stack mt" data-validar>
                    <?= csrf_field() ?><input type="hidden" name="tabla" value="<?= $tabla ?>">
                    <div class="row">
                        <input class="input" name="nombre" required minlength="2" maxlength="60" placeholder="Nuevo…" aria-label="Nuevo nombre" style="flex:1">
                        <?php if ($tabla === 'unidad'): ?><input class="input" name="abreviatura" required maxlength="10" placeholder="Abrev." style="max-width:80px" aria-label="Abreviatura"><?php endif; ?>
                        <?php if ($tabla === 'categoria'): ?>
                            <select class="select" name="tipo" required aria-label="Tipo" style="max-width:120px"><option value="PIEZA">Pieza</option><option value="MATERIAL">Material</option></select>
                        <?php endif; ?>
                        <button class="btn btn-outline btn-sm"><?= icono('mas', 15) ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="faint" style="font-size:13px;margin-top:14px">Los elementos de catálogo no se borran: si ya se usaron en compras o ventas, se desactivan para que dejen de aparecer en los formularios.</p>
