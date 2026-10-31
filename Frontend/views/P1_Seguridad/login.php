<?php
/** CU01 · Iniciar sesión */
$titulo = 'Ingresar';
?>
<style>
    .auth-hero { background: linear-gradient(160deg, #3a2a20 0%, #1c140f 100%); }
    .auth-hero .foto-fondo { position: absolute; inset: 0; background: url('/assets/img/piezas/vasija-pajarito.jpg') center / cover; opacity: .28; mix-blend-mode: luminosity; }
    .auth-hero .blob { animation: flotar 14s ease-in-out infinite; }
    .auth-hero .blob.b2 { animation-duration: 11s; animation-direction: reverse; }
    .galeria { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; max-width: 460px; }
    .galeria img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 14px; border: 2px solid rgba(255,255,255,.15); transition: transform .4s cubic-bezier(.16,1,.3,1); }
    .galeria img:hover { transform: translateY(-4px) rotate(-1deg); }
    .auth-hero h2 em { font-style: italic; color: #f1c3a5; }
    .shake { animation: shake .4s; }
    @keyframes shake { 20%, 60% { transform: translateX(-8px); } 40%, 80% { transform: translateX(8px); } }
</style>
<div class="auth">
    <section class="auth-hero">
        <div class="foto-fondo"></div>
        <div class="blob" style="width:420px;height:420px;background:#b5602f;top:-140px;left:-120px"></div>
        <div class="blob b2" style="width:360px;height:360px;background:#5f7f62;bottom:-120px;right:-80px"></div>

        <div class="brand anim" style="padding:0;--i:0">
            <div class="brand-logo"><?= icono('vasija', 22) ?></div>
            <div><strong style="color:#fff">Mitsü</strong><small style="color:rgba(255,255,255,.65)"><?= e(config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü')) ?></small></div>
        </div>

        <div>
            <h2 class="anim" style="--i:1">Barro, fuego y <em>manos que crean.</em></h2>
            <p class="anim" style="--i:2">Clases de modelado y torno, piezas hechas a mano y el inventario del taller, organizados en un solo lugar.</p>
            <div class="galeria anim" style="--i:3">
                <?php foreach (['taza-celeste', 'vasija-sol', 'jarra-andina'] as $img): ?>
                    <img src="/assets/img/piezas/<?= $img ?>.jpg" alt="Pieza de cerámica del taller" loading="lazy">
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hero-cards">
            <?php foreach ([['calendario-ok', 'Reservas en línea', 'Cupos en tiempo real'], ['paquete', 'Inventario', 'Arcilla, esmaltes y alertas'], ['carrito', 'Ventas y caja', 'Nota de venta y arqueo']] as $i => [$ic, $t, $s]): ?>
                <div class="hero-card anim" style="--i:<?= 4 + $i ?>"><?= icono($ic, 22) ?><strong><?= e($t) ?></strong><span><?= e($s) ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="auth-side">
        <div class="auth-box anim">
            <div class="brand" style="padding:0">
                <div class="brand-logo"><?= icono('vasija', 22) ?></div>
                <div><strong>Mitsü</strong><small>Sistema de gestión del taller</small></div>
            </div>
            <h1>Bienvenido al taller</h1>
            <p>Ingrese con su correo electrónico y su contraseña.</p>

            <form method="post" action="/login" class="auth-form" data-validar>
                <?= csrf_field() ?>
                <label class="field">
                    <span>Correo electrónico</span>
                    <span class="input-icon">
                        <?= icono('correo') ?>
                        <input class="input<?= clase_error('correo') ?>" type="email" name="correo" value="<?= e(old('correo')) ?>" required maxlength="120"
                               autocomplete="username" autofocus placeholder="nombre@correo.com">
                    </span>
                    <?= error_campo('correo') ?>
                </label>
                <label class="field">
                    <span>Contraseña</span>
                    <span class="input-icon">
                        <?= icono('candado') ?>
                        <input class="input<?= clase_error('password') ?>" type="password" name="password" id="clave" required maxlength="100"
                               autocomplete="current-password" placeholder="••••••••" style="padding-right:48px">
                        <button type="button" class="btn btn-ghost btn-icon btn-sm trail" data-ver-clave="clave" aria-label="Mostrar contraseña" style="width:34px;height:34px"><?= icono('ver', 17) ?></button>
                    </span>
                    <?= error_campo('password') ?>
                </label>
                <div style="text-align:right;margin-top:-8px"><a href="/recuperar" style="font-size:13.5px;font-weight:600">¿Olvidaste tu contraseña?</a></div>
                <button type="submit" class="btn btn-primary btn-block" style="height:48px;font-size:15px">Ingresar <?= icono('flecha', 17) ?></button>
            </form>

            <div class="demo">
                ¿Quieres tomar clases de cerámica? <a href="/registro"><strong>Crea tu cuenta de alumno</strong></a> y reserva tu lugar.
            </div>
        </div>
    </section>
</div>
