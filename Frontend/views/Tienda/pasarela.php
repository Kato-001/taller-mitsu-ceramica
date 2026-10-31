<?php
/**
 * CU21 · Pasarela de pago: QR bancario, tarjeta y PayPal (modo demostración)
 * @var array $pedido @var array $detalle @var array $pagos
 */
$p = $pedido;
$titulo = 'Pagar pedido ' . $p['numero'];
$scripts = ['pasarela.js'];
$metodo = in_array(entrada('metodo'), ['qr', 'tarjeta', 'paypal'], true) ? entrada('metodo') : 'qr';
$segundos = max(0, strtotime($p['expira_en']) - time());
$rechazos = array_filter($pagos, fn($x) => $x['estado'] === 'RECHAZADO');
?>
<div class="pasarela">
    <div class="page-head">
        <div>
            <div class="eyebrow"><?= icono('candado', 14) ?> Pago seguro</div>
            <h1>Pagar <?= bs($p['total']) ?></h1>
            <p>Pedido <strong class="mono"><?= e($p['numero']) ?></strong> · tus piezas están reservadas por <span class="cronometro" data-segundos="<?= $segundos ?>"><?= sprintf('%02d:%02d', intdiv($segundos, 60), $segundos % 60) ?></span> minutos.</p>
        </div>
    </div>

    <div class="aviso-demo anim"><?= icono('info', 18) ?><div><strong>Pasarela en modo de demostración.</strong> No se cobra dinero real. Para probar con tarjeta use <span class="mono">4242 4242 4242 4242</span> (aprobada) o <span class="mono">4000 0000 0000 0002</span> (rechazada), con cualquier fecha futura y CVV de 3 dígitos.</div></div>

    <?php if ($rechazos): ?>
        <div class="alert alert-warn anim"><?= icono('alerta') ?><div>Intento anterior rechazado: <?= e(reset($rechazos)['detalle']) ?>. Puedes intentar otra vez o elegir otro método.</div></div>
    <?php endif; ?>

    <div class="t-dos">
        <div class="card card-pad anim">
            <div class="metodos" role="tablist" aria-label="Método de pago">
                <button type="button" class="metodo" role="tab" data-metodo="qr" aria-selected="<?= $metodo === 'qr' ? 'true' : 'false' ?>"><?= icono('caja', 22) ?>QR bancario<small>Cualquier banco</small></button>
                <button type="button" class="metodo" role="tab" data-metodo="tarjeta" aria-selected="<?= $metodo === 'tarjeta' ? 'true' : 'false' ?>"><?= icono('etiqueta', 22) ?>Tarjeta<small>Débito o crédito</small></button>
                <button type="button" class="metodo" role="tab" data-metodo="paypal" aria-selected="<?= $metodo === 'paypal' ? 'true' : 'false' ?>"><?= icono('ok-circulo', 22) ?>PayPal<small>Cuenta PayPal</small></button>
            </div>

            <!-- QR bancario -->
            <section class="panel-pago" data-panel="qr" <?= $metodo === 'qr' ? '' : 'hidden' ?>>
                <div class="qr-caja">
                    <img src="/assets/img/pago/qr-taller.jpg" alt="Código QR de pago del Taller Mitsü">
                    <div class="stack">
                        <h2 style="font-size:18px">Escanea con la app de tu banco</h2>
                        <ol class="soft" style="font-size:14px;padding-left:18px;margin:0;display:grid;gap:4px">
                            <li>Abre tu banca móvil y elige <strong>Pagar con QR</strong>.</li>
                            <li>Escanea el código e ingresa el monto exacto: <strong><?= bs($p['total']) ?></strong>.</li>
                            <li>En la glosa escribe <strong class="mono"><?= e($p['numero']) ?></strong>.</li>
                            <li>Toca <strong>Ya pagué</strong>. El taller confirmará el depósito.</li>
                        </ol>
                        <form method="post" action="/pago/qr" class="stack" data-validar>
                            <?= csrf_field() ?><input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                            <label class="field"><span>N.º de transacción (opcional)</span>
                                <input class="input" name="comprobante" maxlength="30" pattern="[A-Za-z0-9\-]{4,30}" data-msg="Letras, números y guiones (4 a 30)." placeholder="Ej. 784512369"></label>
                            <button class="btn btn-primary btn-block"><?= icono('check') ?> Ya pagué</button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Tarjeta -->
            <section class="panel-pago" data-panel="tarjeta" <?= $metodo === 'tarjeta' ? '' : 'hidden' ?>>
                <div class="grid-2" style="align-items:start">
                    <div class="tarjeta-visual" aria-hidden="true">
                        <div class="row-between"><span class="chip"></span><span id="tv-marca">TARJETA</span></div>
                        <div class="num" id="tv-numero">•••• •••• •••• ••••</div>
                        <div class="fila"><span id="tv-titular">NOMBRE DEL TITULAR</span><span id="tv-vence">MM/AA</span></div>
                    </div>
                    <form method="post" action="/pago/tarjeta" class="stack" id="form-tarjeta" data-validar autocomplete="off">
                        <?= csrf_field() ?><input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                        <label class="field"><span>Número de tarjeta</span>
                            <input class="input mono<?= clase_error('numero') ?>" name="numero" id="tj-numero" inputmode="numeric" required maxlength="23" pattern="[0-9 ]{13,23}" data-msg="Número de tarjeta no válido." placeholder="0000 0000 0000 0000" autocomplete="cc-number">
                            <?= error_campo('numero') ?></label>
                        <label class="field"><span>Nombre del titular</span>
                            <input class="input<?= clase_error('titular') ?>" name="titular" id="tj-titular" value="<?= e(old('titular')) ?>" required minlength="3" maxlength="80" autocomplete="cc-name" style="text-transform:uppercase">
                            <?= error_campo('titular') ?></label>
                        <div class="grid-2">
                            <label class="field"><span>Vence</span>
                                <input class="input mono<?= clase_error('vence') ?>" name="vence" id="tj-vence" value="<?= e(old('vence')) ?>" required maxlength="5" pattern="(0[1-9]|1[0-2])/[0-9]{2}" data-msg="Formato MM/AA." placeholder="MM/AA" autocomplete="cc-exp">
                                <?= error_campo('vence') ?></label>
                            <label class="field"><span>CVV</span>
                                <input class="input mono<?= clase_error('cvv') ?>" name="cvv" type="password" required maxlength="4" pattern="[0-9]{3,4}" data-msg="3 o 4 dígitos." inputmode="numeric" autocomplete="cc-csc">
                                <?= error_campo('cvv') ?></label>
                        </div>
                        <button class="btn btn-primary btn-block" style="height:48px"><?= icono('candado') ?> Pagar <?= bs($p['total']) ?></button>
                        <small class="faint">No guardamos el número completo ni el CVV: solo la marca y los 4 últimos dígitos.</small>
                    </form>
                </div>
            </section>

            <!-- PayPal -->
            <section class="panel-pago" data-panel="paypal" <?= $metodo === 'paypal' ? '' : 'hidden' ?>>
                <div class="stack" style="text-align:center;justify-items:center;padding:12px 0">
                    <?= icono('ok-circulo', 44) ?>
                    <h2 style="font-size:18px">Paga con tu cuenta PayPal</h2>
                    <p class="soft m0" style="max-width:420px">Te llevaremos a la pantalla de PayPal para aprobar el pago de <strong><?= bs($p['total']) ?></strong>. Al aprobar, vuelves aquí con tu comprobante.</p>
                    <a href="<?= e(url('/pago/paypal', ['pedido' => $p['id']])) ?>" class="btn btn-primary" style="height:48px;padding:0 26px"><?= icono('flecha') ?> Continuar a PayPal</a>
                </div>
            </section>
        </div>

        <div class="card card-pad stack anim">
            <h2 style="font-size:18px">Resumen</h2>
            <?php foreach ($detalle as $d): ?>
                <div class="row-between" style="font-size:14px"><span><?= (int) $d['cantidad'] ?> × <?= e($d['nombre']) ?></span><strong class="mono"><?= bs($d['subtotal']) ?></strong></div>
            <?php endforeach; ?>
            <div class="row-between" style="border-top:1px solid var(--border);padding-top:10px"><span class="soft">Total</span><span class="total-grande"><?= bs($p['total']) ?></span></div>
            <div class="kv-list">
                <div class="kv"><span>Entrega</span><strong><?= $p['entrega'] === 'RETIRO' ? 'Retiro en el taller' : 'Envío' ?></strong></div>
                <div class="kv"><span>Comprobante a</span><strong><?= e($p['correo']) ?></strong></div>
            </div>
            <form method="post" action="/mis-pedidos/cancelar" data-confirm="¿Cancelar el pedido?" data-confirm-texto="Las piezas vuelven a la tienda." data-confirm-ok="Sí, cancelar" data-peligro>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-danger-ghost btn-block" data-sin-carga="1">Cancelar pedido</button>
            </form>
        </div>
    </div>
</div>
