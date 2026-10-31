<?php
/**
 * CU18 · Punto de venta: carrito, cliente, método de pago y cobro
 * @var ?array $caja @var array $piezas @var array $clientes @var array $metodos
 */
$titulo = 'Vender';
$scripts = ['ventas.js'];
$clienteNuevo = $_SESSION['cliente_nuevo'] ?? null;
unset($_SESSION['cliente_nuevo']);
$catalogo = array_map(fn($p) => ['id' => (int) $p['id'], 'codigo' => $p['codigo'], 'nombre' => $p['nombre'],
    'precio' => (float) $p['precio'], 'stock' => (int) $p['stock'], 'foto' => Pieza::foto($p)], $piezas);
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= icono('carrito', 14) ?> Ventas</div>
        <h1>Vender piezas</h1>
        <p><?= $caja ? 'Caja abierta desde las ' . e(date('H:i', strtotime($caja['abierta_en']))) . ' por ' . e($caja['usuario']) . '.' : 'Para vender, primero abra la caja.' ?></p>
    </div>
    <a href="/ventas" class="btn btn-outline"><?= icono('recibo') ?> Ventas registradas</a>
</div>

<?php if (!$caja): ?>
    <div class="card card-pad empty anim">
        <?= icono('caja', 40) ?><div><strong>La caja está cerrada</strong></div>
        <div class="faint">El sistema no permite vender sin una caja abierta (así cuadra el arqueo).</div>
        <?php if (Auth::puede('caja.gestionar')): ?><a href="/caja" class="btn btn-primary" style="margin-top:12px"><?= icono('caja') ?> Abrir caja</a><?php endif; ?>
    </div>
<?php else: ?>
<div class="pos">
    <div class="stack">
        <div class="card toolbar">
            <div class="input-icon" style="flex:1"><?= icono('buscar') ?><input class="input" type="search" id="buscar-pieza" placeholder="Buscar pieza por nombre o código…" aria-label="Buscar pieza"></div>
        </div>
        <div class="piezas-grid" id="catalogo" data-escalonar>
            <?php foreach ($catalogo as $p): ?>
                <button type="button" class="pieza-card" data-pieza="<?= $p['id'] ?>" data-buscar="<?= e(mb_strtolower($p['nombre'] . ' ' . $p['codigo'])) ?>">
                    <div class="foto"><?= $p['foto'] ? '<img src="' . e($p['foto']) . '" alt="" loading="lazy">' : icono('vasija', 36) ?></div>
                    <div class="cuerpo">
                        <span class="faint mono" style="font-size:11.5px"><?= e($p['codigo']) ?> · stock <?= $p['stock'] ?></span>
                        <strong><?= e($p['nombre']) ?></strong>
                        <span class="precio"><?= bs($p['precio']) ?></span>
                    </div>
                </button>
            <?php endforeach; ?>
        </div>
        <?php if (!$catalogo): ?><div class="card empty">No hay piezas con stock a la venta.</div><?php endif; ?>
    </div>

    <form method="post" action="/ventas/guardar" class="card card-pad carrito stack" id="form-venta">
        <?= csrf_field() ?>
        <input type="hidden" name="carrito" id="carrito-json" value="[]">
        <h2 style="font-size:18px"><?= icono('carrito', 19) ?> Venta actual</h2>
        <div id="carrito-items"><p class="faint m0" id="carrito-vacio">Toque una pieza para agregarla.</p></div>
        <?= error_campo('carrito') ?>
        <div class="row-between"><span class="soft">Total</span><span class="total-grande" id="total">Bs 0,00</span></div>

        <label class="field"><span>Cliente *</span>
            <select class="select" name="cliente_id" required>
                <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>" <?= (int) ($clienteNuevo ?? old('cliente_id', '1')) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre'] . ($c['ci_nit'] ? ' · ' . $c['ci_nit'] : '')) ?></option><?php endforeach; ?>
            </select>
            <small><a href="#" data-abrir-cliente>+ Registrar cliente nuevo</a></small></label>
        <label class="field"><span>Método de pago *</span>
            <div class="chips">
                <?php foreach ($metodos as $i => $m): ?>
                    <label class="chip-check"><input type="radio" name="metodo_pago_id" value="<?= $m['id'] ?>" data-efectivo="<?= mb_strtolower($m['nombre']) === 'efectivo' ? '1' : '0' ?>" <?= (string) old('metodo_pago_id', (string) $metodos[0]['id']) === (string) $m['id'] ? 'checked' : '' ?> required><span><?= e($m['nombre']) ?></span></label>
                <?php endforeach; ?>
            </div></label>
        <label class="field" id="campo-recibido"><span>Monto recibido (Bs)</span>
            <input class="input" type="number" name="recibido" id="recibido" min="0" max="1000000" step="0.5" value="<?= e(old('recibido')) ?>">
            <small id="cambio">Cambio: Bs 0,00</small></label>
        <button class="btn btn-primary btn-block" style="height:50px;font-size:15px" id="btn-cobrar" disabled><?= icono('check') ?> Cobrar y emitir nota</button>
    </form>
</div>

<div class="modal-layer" id="modal-cliente" role="dialog" aria-modal="true" aria-labelledby="mcl-titulo">
    <div class="modal-backdrop" data-cerrar-cliente></div>
    <form method="post" action="/clientes/guardar" class="modal" data-validar>
        <?= csrf_field() ?><input type="hidden" name="origen" value="venta">
        <div class="modal-head"><div class="modal-ico" style="background:var(--primary-soft);color:var(--primary)"><?= icono('usuario-mas', 22) ?></div>
            <div><h2 id="mcl-titulo">Nuevo cliente</h2><p>Quedará seleccionado para esta venta.</p></div>
            <button type="button" class="btn btn-ghost btn-icon" data-cerrar-cliente aria-label="Cerrar"><?= icono('cerrar') ?></button></div>
        <div class="modal-body grid-2">
            <label class="field span-2"><span>Nombre o razón social *</span><input class="input" name="nombre" required minlength="3" maxlength="120"></label>
            <label class="field"><span>CI / NIT</span><input class="input" name="ci_nit" maxlength="20" pattern="\d{4,15}(-[0-9A-Z]{1,3})?" data-msg="Solo números (4 a 15)."></label>
            <label class="field"><span>Teléfono</span><input class="input" type="tel" name="telefono" maxlength="20" pattern="[0-9+\- ]{7,20}" data-msg="7 a 20 dígitos."></label>
            <label class="field span-2"><span>Correo</span><input class="input" type="email" name="correo" maxlength="120"></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-cerrar-cliente>Cancelar</button><button class="btn btn-primary">Guardar cliente</button></div>
    </form>
</div>
<script type="application/json" id="catalogo-json"><?= json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
