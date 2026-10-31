<?php
/**
 * CU21 · Aprobación de pago con PayPal (sandbox simulado, sin marca oficial)
 * @var array $pedido
 */
$p = $pedido;
$titulo = 'PayPal · modo de prueba';
?>
<div class="auth-centro">
    <div class="card card-pad auth-tarjeta anim paypal-caja">
        <div class="row-between" style="margin-bottom:12px">
            <strong style="font-size:22px;font-family:var(--serif)">PayPal</strong>
            <span class="badge badge-info">Sandbox de prueba</span>
        </div>
        <p class="soft m0">Comercio: <strong>Taller de Cerámica Mitsü</strong></p>
        <div class="row-between" style="margin:14px 0;padding:14px;border-radius:12px;background:var(--bg-sunken)">
            <span>Pedido <span class="mono"><?= e($p['numero']) ?></span></span><strong style="font-size:20px"><?= bs($p['total']) ?></strong>
        </div>
        <div class="aviso-demo"><?= icono('info', 18) ?><div>Simulación del sandbox de PayPal: escribe cualquier correo y una contraseña de al menos 4 caracteres. No se cobra dinero real.</div></div>
        <form method="post" action="/pago/paypal" class="stack" data-validar>
            <?= csrf_field() ?><input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
            <label class="field"><span>Correo de PayPal</span><input class="input" type="email" name="cuenta" required maxlength="120" value="<?= e($p['correo']) ?>"></label>
            <label class="field"><span>Contraseña</span><input class="input" type="password" name="clave" required minlength="4" maxlength="100"></label>
            <button class="btn btn-primary btn-block" name="accion" value="aprobar" style="height:48px"><?= icono('check') ?> Aprobar pago de <?= bs($p['total']) ?></button>
            <button class="btn btn-ghost btn-block" name="accion" value="cancelar" formnovalidate data-sin-carga="1">Cancelar y volver al taller</button>
        </form>
    </div>
</div>
