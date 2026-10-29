<?php
/**
 * Layout principal (usuarios con sesión): menú lateral según permisos,
 * barra superior y zona de contenido.
 * @var string $contenido HTML de la vista
 */
$u = Auth::usuario();
$ruta = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// Menú: [título del grupo => [[ruta, etiqueta, icono, permisos…]]]
$menu = [
    'Mi espacio' => [
        ['/portal', 'Inicio', 'inicio', 'portal.alumno'],
        ['/portal/reservar', 'Reservar clase', 'calendario-mas', 'portal.alumno'],
        ['/portal/perfil', 'Mi perfil', 'cuenta', 'portal.alumno'],
    ],
    'General' => [
        ['/panel', 'Panel', 'panel', 'panel.ver'],
    ],
    'Clases' => [
        ['/clases', Auth::puede('sesiones.gestionar', 'reservas.gestionar', 'asistencia.registrar') ? 'Calendario de clases' : 'Mis clases', 'agenda',
            'sesiones.gestionar', 'reservas.gestionar', 'asistencia.registrar', 'clases.propias'],
        ['/alumnos', 'Alumnos', 'escuela', 'alumnos.gestionar'],
        ['/talleres', 'Talleres', 'paleta', 'talleres.gestionar'],
        ['/horarios', 'Horarios', 'reloj', 'talleres.gestionar'],
        ['/maestras', 'Maestras', 'usuario-ok', 'maestras.gestionar'],
    ],
    'Ventas' => [
        ['/ventas/nueva', 'Vender', 'carrito', 'ventas.registrar'],
        ['/ventas', 'Ventas', 'recibo', 'ventas.registrar', 'ventas.anular'],
        ['/caja', 'Caja', 'caja', 'caja.gestionar'],
        ['/piezas', 'Piezas de cerámica', 'vasija', 'piezas.gestionar'],
        ['/clientes', 'Clientes', 'usuarios', 'clientes.gestionar'],
    ],
    'Inventario' => [
        ['/materiales', 'Materiales', 'paquete', 'materiales.gestionar'],
        ['/compras', 'Compras', 'camion', 'compras.gestionar'],
        ['/proveedores', 'Proveedores', 'tienda', 'proveedores.gestionar'],
    ],
    'Administración' => [
        ['/usuarios', 'Usuarios', 'usuarios', 'usuarios.gestionar'],
        ['/roles', 'Roles y permisos', 'escudo', 'roles.gestionar'],
        ['/configuracion', 'Configuración', 'ajustes', 'configuracion.gestionar'],
        ['/bitacora', 'Bitácora', 'bitacora', 'bitacora.ver'],
    ],
];
$exactas = ['/portal', '/ventas', '/clases', '/piezas', '/compras', '/materiales', '/alumnos'];
$activo = function (string $destino) use ($ruta, $exactas): bool {
    if (in_array($destino, $exactas, true)) {
        return $ruta === $destino || (str_starts_with($ruta, $destino . '/') && !in_array($ruta, ['/ventas/nueva', '/portal/reservar', '/portal/perfil', '/compras/nueva'], true));
    }
    return $ruta === $destino || str_starts_with($ruta, $destino . '/');
};
$taller = config('NOMBRE_TALLER', 'Taller de Cerámica Mitsü');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Mitsü') ?> · Mitsü</title>
    <meta name="theme-color" content="#a8552f">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script>try { var t = localStorage.getItem('mitsu-tema'); document.documentElement.dataset.theme = t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch (e) {}</script>
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/componentes.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" aria-label="Menú principal">
        <a href="<?= e(Auth::inicio()) ?>" class="brand">
            <span class="brand-logo"><?= icono('vasija', 22) ?></span>
            <span><strong>Mitsü</strong><small>Taller de cerámica</small></span>
        </a>
        <nav>
            <?php foreach ($menu as $grupo => $items):
                $visibles = array_filter($items, fn($i) => Auth::puede(...array_slice($i, 3)));
                if (!$visibles) continue; ?>
                <div class="nav-title"><?= e($grupo) ?></div>
                <?php foreach ($visibles as [$destino, $etiqueta, $ico]): ?>
                    <a href="<?= e($destino) ?>" class="nav-link<?= $activo($destino) ? ' active' : '' ?>"<?= $activo($destino) ? ' aria-current="page"' : '' ?>>
                        <?= icono($ico, 19) ?><?= e($etiqueta) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (!Auth::puede('portal.alumno')): ?>
                <div class="nav-title">Cuenta</div>
                <a href="/cuenta" class="nav-link<?= $ruta === '/cuenta' ? ' active' : '' ?>"><?= icono('cuenta', 19) ?>Mi cuenta</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="avatar"><?= e(iniciales($u['nombre_completo'] ?? '')) ?></div>
            <div class="who">
                <strong><?= e($u['nombre_completo'] ?? '') ?></strong>
                <small><?= e(nombre_rol($u['rol'] ?? '')) ?></small>
            </div>
            <form method="post" action="/salir" class="inline">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-icon" title="Cerrar sesión" aria-label="Cerrar sesión" data-sin-carga="1"><?= icono('salir') ?></button>
            </form>
        </div>
    </aside>
    <div class="backdrop" hidden></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon menu-btn" data-menu aria-label="Abrir menú"><?= icono('menu', 20) ?></button>
            <div class="crumbs"><?= e($taller) ?> / <b><?= e($titulo ?? '') ?></b></div>
            <div class="spacer"></div>
            <span class="faint no-print" style="font-size:13px;font-weight:600"><?= e(fecha_larga(hoy())) ?></span>
            <button class="btn btn-ghost btn-icon" data-tema aria-label="Cambiar tema claro/oscuro" title="Cambiar tema"><?= icono('luna', 19) ?></button>
        </header>
        <main class="content page" id="contenido">
            <?= $contenido ?>
        </main>
    </div>
</div>
<?= View::parcial('toasts') ?>
<?= View::parcial('modal_confirmar') ?>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<?php if (!empty($scripts)): foreach ($scripts as $s): ?>
<script src="<?= asset('assets/js/' . $s) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
