<?php
/**
 * Taller de Cerámica Mitsü · Controlador frontal
 * ---------------------------------------------------------------------
 * Todas las peticiones llegan aquí (Vercel: vercel.json · Apache: .htaccess ·
 * servidor de PHP: router.php) y se envían al controlador correspondiente.
 */

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$r = new Router();

// --- Ciclo I · P1 Seguridad y configuración ---------------------------
$r->get('/', [TiendaController::class, 'inicio'])
  ->get('/login', [AuthController::class, 'formulario'])                       // CU01
  ->post('/login', [AuthController::class, 'ingresar'])
  ->post('/salir', [AuthController::class, 'salir'])
  ->get('/recuperar', [RecuperacionController::class, 'formulario'])           // CU02
  ->post('/recuperar', [RecuperacionController::class, 'enviar'])
  ->get('/recuperar/codigo', [RecuperacionController::class, 'codigo'])
  ->post('/recuperar/codigo', [RecuperacionController::class, 'restablecer'])
  ->get('/panel', [PanelController::class, 'index'])
  ->get('/usuarios', [UsuarioController::class, 'index'])                      // CU03
  ->get('/usuarios/nuevo', [UsuarioController::class, 'crear'])
  ->get('/usuarios/editar', [UsuarioController::class, 'editar'])
  ->post('/usuarios/guardar', [UsuarioController::class, 'guardar'])
  ->post('/usuarios/estado', [UsuarioController::class, 'estado'])
  ->get('/roles', [RolController::class, 'index'])                             // CU04
  ->post('/roles/guardar', [RolController::class, 'guardar'])
  ->post('/roles/permisos', [RolController::class, 'permisos'])
  ->post('/roles/estado', [RolController::class, 'estado'])
  ->get('/bitacora', [SistemaController::class, 'bitacora'])                   // CU05
  ->get('/configuracion', [SistemaController::class, 'configuracion'])         // CU06
  ->post('/configuracion', [SistemaController::class, 'guardarConfiguracion'])
  ->post('/catalogos/guardar', [SistemaController::class, 'guardarCatalogo'])
  ->post('/catalogos/estado', [SistemaController::class, 'estadoCatalogo'])
  ->get('/cuenta', [SistemaController::class, 'cuenta'])
  ->post('/cuenta/password', [SistemaController::class, 'password']);

// --- Ciclo II · P2 Clases y reservas ----------------------------------
$r->get('/maestras', [MaestraController::class, 'index'])                      // CU07
  ->get('/maestras/nueva', [MaestraController::class, 'crear'])
  ->get('/maestras/editar', [MaestraController::class, 'editar'])
  ->post('/maestras/guardar', [MaestraController::class, 'guardar'])
  ->post('/maestras/estado', [MaestraController::class, 'estado'])
  ->get('/talleres', [TallerController::class, 'index'])                       // CU08
  ->post('/talleres/guardar', [TallerController::class, 'guardar'])
  ->post('/talleres/estado', [TallerController::class, 'estado'])
  ->get('/horarios', [TallerController::class, 'horarios'])                    // CU09
  ->post('/horarios/guardar', [TallerController::class, 'guardarHorario'])
  ->post('/horarios/estado', [TallerController::class, 'estadoHorario'])
  ->get('/clases', [ClaseController::class, 'calendario'])
  ->get('/clases/ver', [ClaseController::class, 'ver'])
  ->post('/clases/generar', [ClaseController::class, 'generar'])
  ->post('/clases/cancelar', [ClaseController::class, 'cancelar'])
  ->post('/clases/maestra', [ClaseController::class, 'maestra'])
  ->post('/clases/reservar', [ClaseController::class, 'reservar'])             // CU10
  ->post('/reservas/cancelar', [ClaseController::class, 'cancelarReserva'])
  ->post('/clases/asistencia', [ClaseController::class, 'asistencia'])         // CU11
  ->get('/alumnos', [AlumnoController::class, 'index'])                        // CU12
  ->get('/alumnos/ver', [AlumnoController::class, 'ver'])
  ->get('/alumnos/nuevo', [AlumnoController::class, 'crear'])
  ->get('/alumnos/editar', [AlumnoController::class, 'editar'])
  ->post('/alumnos/guardar', [AlumnoController::class, 'guardar'])
  ->post('/alumnos/estado', [AlumnoController::class, 'estado'])
  ->get('/registro', [AuthController::class, 'registro'])
  ->post('/registro', [AuthController::class, 'registrar'])
  ->get('/portal', [PortalController::class, 'inicio'])
  ->get('/portal/reservar', [PortalController::class, 'reservar'])
  ->post('/portal/reservar', [PortalController::class, 'guardarReserva'])
  ->post('/portal/cancelar', [PortalController::class, 'cancelar'])
  ->get('/portal/perfil', [PortalController::class, 'perfil'])
  ->post('/portal/perfil', [PortalController::class, 'guardarPerfil']);

// --- Ciclo III · P3 Inventario y compras · P4 Ventas y caja ------------
$r->get('/materiales', [InventarioController::class, 'materiales'])            // CU13
  ->get('/materiales/form', [InventarioController::class, 'formMaterial'])
  ->post('/materiales/guardar', [InventarioController::class, 'guardarMaterial'])
  ->post('/materiales/estado', [InventarioController::class, 'estadoMaterial'])
  ->get('/materiales/kardex', [InventarioController::class, 'kardex'])
  ->post('/materiales/movimiento', [InventarioController::class, 'movimiento']) // CU16
  ->get('/proveedores', [InventarioController::class, 'proveedores'])          // CU14
  ->post('/proveedores/guardar', [InventarioController::class, 'guardarProveedor'])
  ->post('/proveedores/estado', [InventarioController::class, 'estadoProveedor'])
  ->get('/compras', [CompraController::class, 'index'])                        // CU15
  ->get('/compras/nueva', [CompraController::class, 'nueva'])
  ->post('/compras/guardar', [CompraController::class, 'guardar'])
  ->get('/compras/ver', [CompraController::class, 'ver'])
  ->post('/compras/anular', [CompraController::class, 'anular'])
  ->get('/piezas', [PiezaController::class, 'index'])                          // CU17
  ->get('/piezas/form', [PiezaController::class, 'form'])
  ->post('/piezas/guardar', [PiezaController::class, 'guardar'])
  ->post('/piezas/estado', [PiezaController::class, 'estado'])
  ->get('/piezas/kardex', [PiezaController::class, 'kardex'])
  ->post('/piezas/movimiento', [PiezaController::class, 'movimiento'])
  ->get('/piezas/foto', [PiezaController::class, 'foto'])
  ->get('/ventas/nueva', [VentaController::class, 'nueva'])                    // CU18
  ->post('/ventas/guardar', [VentaController::class, 'guardar'])
  ->get('/ventas', [VentaController::class, 'index'])
  ->get('/ventas/ver', [VentaController::class, 'ver'])
  ->post('/ventas/anular', [VentaController::class, 'anular'])
  ->get('/clientes', [VentaController::class, 'clientes'])
  ->post('/clientes/guardar', [VentaController::class, 'guardarCliente'])
  ->post('/clientes/estado', [VentaController::class, 'estadoCliente'])
  ->get('/caja', [VentaController::class, 'caja'])                             // CU19
  ->post('/caja/abrir', [VentaController::class, 'abrirCaja'])
  ->post('/caja/cerrar', [VentaController::class, 'cerrarCaja']);

// --- Ciclo III (ampliación) · P4 Tienda en línea y pasarela de pago -----
$r->get('/tienda', [TiendaController::class, 'catalogo'])                     // CU20
  ->get('/tienda/pieza', [TiendaController::class, 'pieza'])
  ->get('/tienda/carrito', [TiendaController::class, 'verCarrito'])
  ->post('/tienda/carrito/agregar', [TiendaController::class, 'agregar'])
  ->post('/tienda/carrito/actualizar', [TiendaController::class, 'actualizar'])
  ->get('/tienda/checkout', [TiendaController::class, 'checkout'])
  ->post('/tienda/checkout', [TiendaController::class, 'confirmar'])
  ->get('/tienda/ingresar', [AuthController::class, 'ingresarTienda'])
  ->get('/tienda/registro', [TiendaController::class, 'registro'])
  ->post('/tienda/registro', [TiendaController::class, 'registrar'])
  ->get('/auth/google', [GoogleController::class, 'iniciar'])
  ->get('/auth/google/callback', [GoogleController::class, 'callback'])
  ->get('/mis-pedidos', [TiendaController::class, 'misPedidos'])
  ->get('/mis-pedidos/ver', [TiendaController::class, 'verPedido'])
  ->post('/mis-pedidos/cancelar', [TiendaController::class, 'cancelarPedido'])
  ->get('/pago', [PagoController::class, 'pasarela'])                          // CU21
  ->post('/pago/tarjeta', [PagoController::class, 'tarjeta'])
  ->post('/pago/qr', [PagoController::class, 'qr'])
  ->get('/pago/paypal', [PagoController::class, 'paypal'])
  ->post('/pago/paypal', [PagoController::class, 'confirmarPaypal'])
  ->get('/pedidos', [PedidoController::class, 'index'])                        // CU22
  ->get('/pedidos/ver', [PedidoController::class, 'ver'])
  ->post('/pedidos/verificar-qr', [PedidoController::class, 'verificarQr'])
  ->post('/pedidos/entregar', [PedidoController::class, 'entregar'])
  ->post('/pedidos/cancelar', [PedidoController::class, 'cancelar']);

// Cabeceras de seguridad básicas
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$r->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
