<?php
/**
 * CU23 Consultar con filtros · CU24 Exportar reportes · CU25 Comando de voz
 * @var array $tipos @var array $q @var array $opciones @var ?array $r @var ?string $error
 * @var string $frase @var array $guardados @var Paginador $pag
 */
$titulo = 'Reportes';
$scripts = ['reportes.js'];
$campos = $tipos[$q['tipo']]['campos'];
$meta = [];
foreach ($campos as $k => $c) {
    $meta[$k] = ['tipo' => Reporte::familia($c[2]), 'opciones' => array_map(fn($v) => [$v, Reporte::etiqueta($v)], $opciones[$k] ?? [])];
}
$filtros = $q['f'] ?: [['c' => array_key_first($campos), 'o' => '', 'v' => '', 'w' => '']];
$filaFiltro = function ($i, array $f) use ($campos): void { ?>
    <div class="filtro-fila" data-filtro>
        <select class="select" name="f[<?= $i ?>][c]" data-campo aria-label="Campo">
            <?php foreach ($campos as $k => $c): ?><option value="<?= $k ?>" <?= $f['c'] === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="f[<?= $i ?>][o]" data-operador data-valor="<?= e($f['o']) ?>" aria-label="Condición"></select>
        <span class="filtro-valores" data-valores data-v="<?= e($f['v']) ?>" data-w="<?= e($f['w']) ?>" data-nombre="f[<?= $i ?>]"></span>
        <button type="button" class="btn btn-ghost btn-icon btn-danger-ghost" data-quitar title="Quitar filtro" aria-label="Quitar filtro"><?= icono('basura', 16) ?></button>
    </div>
<?php };
?>
<div class="page-head no-print">
    <div>
        <div class="eyebrow"><?= icono('reporte', 14) ?> Reportes</div>
        <h1>Reportes personalizados</h1>
        <p>Elija qué consultar, combine los filtros que necesite y descargue el resultado en PDF, Excel o CSV.</p>
    </div>
</div>

<form class="card card-pad anim voz-caja no-print" method="get" action="/reportes/voz" id="form-voz">
    <button type="button" class="btn btn-primary voz-boton" id="voz-boton" aria-label="Dictar un comando"><?= icono('microfono', 22) ?></button>
    <label class="field" style="flex:1"><span>Comando de voz</span>
        <input class="input" name="frase" id="voz-frase" value="<?= e($frase) ?>" maxlength="200" autocomplete="off" placeholder="Diga o escriba: productos con stock mayor a 20"></label>
    <button class="btn btn-outline" data-sin-carga="1"><?= icono('buscar', 16) ?> Consultar</button>
    <small class="faint voz-ayuda" id="voz-estado" aria-live="polite">Ejemplos: “ventas de este mes”, “materiales con stock bajo en PDF”, “compras a crédito”, “pedidos entre 50 y 200”.</small>
</form>

<form class="card anim mt no-print" method="get" action="/reportes" id="form-reporte">
    <div class="card-pad stack">
        <div class="grid-3">
            <label class="field"><span>¿Qué desea consultar?</span>
                <select class="select" name="tipo" id="rep-tipo">
                    <?php foreach ($tipos as $k => $t): ?><option value="<?= $k ?>" <?= $q['tipo'] === $k ? 'selected' : '' ?>><?= e($t['titulo']) ?></option><?php endforeach; ?>
                </select></label>
            <label class="field"><span>Si hay varios filtros</span>
                <select class="select" name="con">
                    <option value="y">Deben cumplirse todos (Y)</option>
                    <option value="o" <?= $q['con'] === 'o' ? 'selected' : '' ?>>Basta que se cumpla uno (O)</option>
                </select></label>
            <label class="field"><span>Agrupar por</span>
                <select class="select" name="agrupar">
                    <option value="">Sin agrupar (detalle)</option>
                    <?php foreach ($campos as $k => $c): ?><option value="<?= $k ?>" <?= $q['agrupar'] === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?>
                </select></label>
        </div>

        <div>
            <div class="section-title">Filtros</div>
            <div class="stack" id="filtros" style="gap:8px" data-max="<?= Reporte::MAX_FILTROS ?>"
                 data-campos="<?= e(json_encode($meta, JSON_UNESCAPED_UNICODE)) ?>"
                 data-operadores="<?= e(json_encode(Reporte::OPERADORES, JSON_UNESCAPED_UNICODE)) ?>"
                 data-sin-valor="<?= e(json_encode(Reporte::SIN_VALOR)) ?>">
                <?php foreach ($filtros as $i => $f) { $filaFiltro($i, $f); } ?>
            </div>
            <template id="plantilla-filtro"><?php $filaFiltro('__i__', ['c' => array_key_first($campos), 'o' => '', 'v' => '', 'w' => '']); ?></template>
            <button type="button" class="btn btn-outline btn-sm" id="agregar-filtro" style="margin-top:10px"><?= icono('mas', 15) ?> Agregar filtro</button>
        </div>

        <details class="rep-opciones" <?= count($q['col']) < count($campos) ? 'open' : '' ?>>
            <summary>Columnas y orden</summary>
            <div class="chips" style="margin:12px 0">
                <?php foreach ($campos as $k => $c): ?>
                    <label class="rep-columna"><input type="checkbox" name="col[]" value="<?= $k ?>" <?= in_array($k, $q['col'], true) ? 'checked' : '' ?>> <?= e($c[0]) ?></label>
                <?php endforeach; ?>
            </div>
            <div class="grid-2">
                <label class="field"><span>Ordenar por</span>
                    <select class="select" name="orden"><?php foreach ($campos as $k => $c): ?><option value="<?= $k ?>" <?= $q['orden'] === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Sentido</span>
                    <select class="select" name="dir"><option value="asc">Ascendente (A–Z, menor a mayor)</option><option value="desc" <?= $q['dir'] === 'desc' ? 'selected' : '' ?>>Descendente (Z–A, mayor a menor)</option></select></label>
            </div>
        </details>

        <div class="row-between">
            <a class="btn btn-ghost" href="<?= e(url('/reportes', ['tipo' => $q['tipo']])) ?>"><?= icono('reiniciar', 16) ?> Limpiar filtros</a>
            <button class="btn btn-primary" data-sin-carga="1"><?= icono('filtro', 16) ?> Generar reporte</button>
        </div>
    </div>
</form>

<?php if ($error): ?><div class="alert alert-error anim mt"><?= icono('alerta') ?><div><?= e($error) ?></div></div><?php endif; ?>
<?php if ($r): ?>
<div class="card anim mt">
    <div class="card-head" style="flex-wrap:wrap;gap:10px">
        <div><h2><?= e($r['titulo']) ?></h2>
            <small class="soft"><?= e(implode(' · ', $r['descripcion'])) ?> · <strong><?= cantidad($r['total']) ?></strong> registro<?= $r['total'] === 1 ? '' : 's' ?></small></div>
        <div class="row no-print">
            <?php foreach (['pdf' => 'documento', 'xlsx' => 'reporte', 'csv' => 'descargar'] as $fm => $ico): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('/reportes/exportar', $q + ['formato' => $fm])) ?>"><?= icono($ico, 16) ?> <?= e(Exportador::FORMATOS[$fm][0]) ?></a>
            <?php endforeach; ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= icono('imprimir', 16) ?> Imprimir</button>
        </div>
    </div>
    <?php if ($r['total'] > count($r['filas'])): ?><div class="alert alert-info" style="margin:12px"><?= icono('info') ?><div>Se muestran los primeros <?= count($r['filas']) ?> registros. Agregue filtros para reducir el resultado.</div></div><?php endif; ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><?php foreach ($r['columnas'] as [$lbl, $tp]): ?><th<?= in_array($tp, ['numero', 'dinero'], true) ? ' style="text-align:right"' : '' ?>><?= e($lbl) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach (array_slice($r['filas'], $pag->offset, $pag->porPagina) as $f): ?>
                <tr><?php foreach ($r['columnas'] as $k => [$lbl, $tp]): ?><td<?= in_array($tp, ['numero', 'dinero'], true) ? ' style="text-align:right" class="mono"' : '' ?>><?= e(Reporte::mostrar($f[$k], $tp)) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <?php if ($r['filas'] && $r['totales']): ?>
                <tr class="total-row"><?php foreach (array_keys($r['columnas']) as $i => $k): ?><td<?= isset($r['totales'][$k]) ? ' style="text-align:right" class="mono"' : '' ?>><strong><?= isset($r['totales'][$k]) ? e(Reporte::mostrar($r['totales'][$k], $r['columnas'][$k][1])) : ($i === 0 ? 'Total' : '') ?></strong></td><?php endforeach; ?></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if (!$r['filas']): ?><div class="empty"><?= icono('reporte', 36) ?><div><strong>No hay registros con esos filtros</strong></div></div><?php endif; ?>
    </div>
    <?= View::parcial('paginacion', ['pag' => $pag]) ?>
</div>

<div class="card card-pad anim mt no-print">
    <div class="section-title">Mis reportes guardados</div>
    <form method="post" action="/reportes/guardar" class="row" style="flex-wrap:wrap">
        <?= csrf_field() ?><input type="hidden" name="consulta" value="<?= e(http_build_query($q)) ?>">
        <input class="input" name="nombre" required minlength="3" maxlength="80" placeholder="Nombre para esta consulta" aria-label="Nombre del reporte" style="max-width:320px">
        <button class="btn btn-outline"><?= icono('mas', 16) ?> Guardar esta consulta</button>
    </form>
    <?php if ($guardados): ?>
        <div class="stack" style="gap:6px;margin-top:14px">
            <?php foreach ($guardados as $g): ?>
                <div class="row-between">
                    <a href="<?= e(url('/reportes', json_decode($g['parametros'], true) ?: [])) ?>"><?= icono('reporte', 15) ?> <strong><?= e($g['nombre']) ?></strong> <span class="faint"><?= e($tipos[$g['tipo']]['titulo'] ?? '') ?></span></a>
                    <form method="post" action="/reportes/eliminar" class="inline" data-confirm="¿Eliminar “<?= e($g['nombre']) ?>”?" data-confirm-texto="Se borra solo la consulta guardada, no los datos." data-confirm-ok="Eliminar" data-peligro>
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $g['id'] ?>">
                        <button class="btn btn-ghost btn-icon btn-sm btn-danger-ghost" aria-label="Eliminar" data-sin-carga="1"><?= icono('basura', 15) ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
