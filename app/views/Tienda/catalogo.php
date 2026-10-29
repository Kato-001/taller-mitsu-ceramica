<?php
/**
 * CU20 · Catálogo de la tienda
 * @var array $piezas @var array $categorias @var array $f
 */
$titulo = 'Tienda';
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('tienda', 14) ?> Tienda en línea</div>
        <h1>Piezas de cerámica</h1>
        <p>Cada pieza es única y hecha a mano en el taller. Elige, agrega al carrito y paga en línea.</p>
    </div>
</div>
<form class="card toolbar anim" method="get" style="margin-bottom:18px">
    <div class="input-icon" style="flex:1"><?= icono('buscar') ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar: taza, vasija, plato…" aria-label="Buscar"></div>
    <select class="select" name="categoria" data-autoenviar aria-label="Categoría">
        <option value="">Todas las categorías</option>
        <?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (string) $f['categoria'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline" data-sin-carga="1">Buscar</button>
</form>
<?= View::parcial('tarjetas_tienda', ['piezas' => $piezas]) ?>
