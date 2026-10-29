<?php
/**
 * Layout de la tienda en línea (pública): cabecera con carrito, contenido y pie.
 * @var string $contenido HTML de la vista
 */
$u = Auth::usuario();
$enCarrito = array_sum(TiendaController::carrito());
$ruta = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$nav = [['/', 'Inicio'], ['/tienda', 'Piezas'], ['/#talleres', 'Clases']];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Tienda') ?> · Mitsü, taller de cerámica</title>
    <meta name="description" content="Piezas de cerámica hechas a mano y clases de modelado y torno en Santa Cruz de la Sierra.">
    <meta name="theme-color" content="#a8552f">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script>try { var t = localStorage.getItem('mitsu-tema'); document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch (e) {}</script>
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/componentes.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/tienda.css') ?>">
</head>
<body class="tienda">
<header class="t-header">
    <div class="t-wrap t-barra">
        <a href="/" class="brand" style="padding:0"><span class="brand-logo"><?= icono('vasija', 20) ?></span><span><strong>Mitsü</strong><small>Taller de cerámica</small></span></a>
        <nav class="t-nav" aria-label="Tienda">
            <?php foreach ($nav as [$href, $txt]): ?>
                <a href="<?= e($href) ?>" class="<?= $ruta === $href ? 'on' : '' ?>"><?= e($txt) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="row" style="gap:6px">
            <button class="btn btn-ghost btn-icon" data-tema aria-label="Cambiar tema claro/oscuro"><?= icono('luna', 19) ?></button>
            <a href="/tienda/carrito" class="btn btn-ghost t-carrito" aria-label="Carrito, <?= $enCarrito ?> pieza(s)"><?= icono('carrito', 19) ?><?php if ($enCarrito): ?><span class="t-cuenta"><?= $enCarrito ?></span><?php endif; ?></a>
            <?php if ($u): ?>
                <details class="t-menu">
                    <summary class="btn btn-outline btn-sm"><span class="avatar" style="width:26px;height:26px;font-size:11px"><?= e(iniciales($u['nombre_completo'])) ?></span><span class="t-solo-ancho"><?= e($u['nombre']) ?></span></summary>
                    <div class="t-menu-lista card">
                        <?php if (Auth::puede('tienda.comprar')): ?><a href="/mis-pedidos"><?= icono('recibo', 16) ?> Mis pedidos</a><?php endif; ?>
                        <?php if (Auth::puede('portal.alumno')): ?><a href="/portal"><?= icono('agenda', 16) ?> Mis clases</a><?php endif; ?>
                        <?php if (Auth::puede('panel.ver')): ?><a href="/panel"><?= icono('panel', 16) ?> Panel del taller</a><?php endif; ?>
                        <form method="post" action="/salir"><?= csrf_field() ?><button type="submit"><?= icono('salir', 16) ?> Cerrar sesión</button></form>
                    </div>
                </details>
            <?php else: ?>
                <a href="/tienda/ingresar" class="btn btn-primary btn-sm"><?= icono('usuario', 16) ?> <span class="t-solo-ancho">Ingresar</span></a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="t-wrap t-contenido" id="contenido">
    <?= $contenido ?>
</main>
<footer class="t-pie">
    <div class="t-wrap t-pie-grid">
        <div>
            <div class="brand" style="padding:0"><span class="brand-logo"><?= icono('vasija', 20) ?></span><span><strong>Mitsü</strong><small><?= e(config('CIUDAD', 'Santa Cruz de la Sierra, Bolivia')) ?></small></span></div>
            <p class="soft" style="font-size:13.5px;max-width:420px">Piezas hechas a mano con arcillas y minerales de Bolivia. Clases de modelado y torno de martes a sábado.</p>
        </div>
        <div class="soft" style="font-size:13.5px">
            <strong style="color:var(--text)">Pagos</strong><br>QR bancario · Tarjeta · PayPal<br>
            <span class="faint">Pasarela en modo de demostración: no se realizan cobros reales.</span>
        </div>
        <div class="soft" style="font-size:13.5px">
            <strong style="color:var(--text)">Contacto</strong><br><?= e(config('TELEFONO')) ?> · <?= e(config('CORREO')) ?><br>
            <a href="/login">Acceso del personal</a>
        </div>
    </div>
    <p class="t-wrap faint" style="font-size:12px;padding-top:0">Proyecto académico · UAGRM FICCT · Ingeniería en Requisitos y Modelado</p>
</footer>
<?= View::parcial('toasts') ?>
<?= View::parcial('modal_confirmar') ?>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<?php if (!empty($scripts)): foreach ($scripts as $s): ?>
<script src="<?= asset('assets/js/' . $s) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
