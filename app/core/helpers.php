<?php
/**
 * Funciones auxiliares usadas en controladores y vistas.
 */

declare(strict_types=1);

/** Escapa un valor para imprimirlo en HTML (previene XSS). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construye una URL interna con parámetros opcionales. */
function url(string $ruta = '/', array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return $ruta . ($params ? '?' . http_build_query($params) : '');
}

/** Ruta de un recurso estático con versión para evitar caché vieja. */
function asset(string $ruta): string
{
    $archivo = BASE_PATH . '/public/' . $ruta;
    $version = is_file($archivo) ? substr((string) filemtime($archivo), -6) : '1';
    return '/' . $ruta . '?v=' . $version;
}

function redirect(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

/** Vuelve a la página anterior (solo rutas internas). */
function volver(string $porDefecto = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $ruta = parse_url($ref, PHP_URL_PATH) ?: $porDefecto;
    $query = parse_url($ref, PHP_URL_QUERY);
    redirect($ruta . ($query ? "?$query" : ''));
}

/** Vuelve a la página anterior solo si es de este mismo sitio. */
function volverSeguro(string $porDefecto): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url($ref, PHP_URL_HOST);
    if ($host && $host !== ($_SERVER['HTTP_HOST'] ?? '') && $host !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        return $porDefecto;
    }
    $ruta = parse_url($ref, PHP_URL_PATH) ?: $porDefecto;
    $q = parse_url($ref, PHP_URL_QUERY);
    return $ruta . ($q ? "?$q" : '');
}

function json_response(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function es_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** Valor de un campo de texto recibido (recortado). */
function entrada(string $clave, string $porDefecto = ''): string
{
    $v = $_POST[$clave] ?? $_GET[$clave] ?? $porDefecto;
    return is_string($v) ? trim($v) : $porDefecto;
}

function entrada_int(string $clave): ?int
{
    $v = $_POST[$clave] ?? $_GET[$clave] ?? null;
    return is_numeric($v) ? (int) $v : null;
}

// ---------------------------------------------------------------------
// Mensajes flash y datos del formulario anterior
// ---------------------------------------------------------------------
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_flash(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Guarda errores y datos enviados para volver a mostrar el formulario. */
function con_errores(array $errores, array $datos = []): void
{
    unset($datos['password'], $datos['password_confirmacion'], $datos['_csrf']);
    $_SESSION['_errores'] = $errores;
    $_SESSION['_old'] = $datos;
}

function old(string $campo, mixed $porDefecto = ''): string
{
    return (string) ($GLOBALS['_old'][$campo] ?? $porDefecto ?? '');
}

function error_campo(string $campo): string
{
    $msg = $GLOBALS['_errores'][$campo] ?? null;
    return $msg ? '<span class="error">' . e($msg) . '</span>' : '';
}

function clase_error(string $campo): string
{
    return isset($GLOBALS['_errores'][$campo]) ? ' invalid' : '';
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

// ---------------------------------------------------------------------
// Formatos
// ---------------------------------------------------------------------
const DIAS_SEMANA = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function fecha(?string $v): string
{
    if (!$v) return '—';
    $t = strtotime($v);
    return date('d', $t) . ' ' . MESES[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function fecha_hora(?string $v): string
{
    return $v ? fecha($v) . ' · ' . date('H:i', strtotime($v)) : '—';
}

function hora(?string $v): string
{
    return $v ? substr($v, 0, 5) : '—';
}

function fecha_larga(string $v): string
{
    $t = strtotime($v);
    return DIAS_SEMANA[(int) date('N', $t)] . ' ' . date('j', $t) . ' de ' . [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ][(int) date('n', $t) - 1];
}

function hace_cuanto(?string $v): string
{
    if (!$v) return 'Nunca';
    $s = time() - strtotime($v);
    return match (true) {
        $s < 60 => 'Hace un momento',
        $s < 3600 => 'Hace ' . intdiv($s, 60) . ' min',
        $s < 86400 => 'Hace ' . intdiv($s, 3600) . ' h',
        $s < 604800 => 'Hace ' . intdiv($s, 86400) . ' d',
        default => fecha($v),
    };
}

function edad(?string $nacimiento): string
{
    return $nacimiento ? (new DateTime($nacimiento))->diff(new DateTime())->y . ' años' : '—';
}

function iniciales(?string $nombre): string
{
    $partes = array_slice(preg_split('/\s+/', trim((string) $nombre)) ?: [], 0, 2);
    return mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), $partes)));
}

function hoy(): string
{
    return date('Y-m-d');
}

/** Valor de la tabla CONFIGURACION (con caché por petición). */
function config(string $clave, string $porDefecto = ''): string
{
    static $cache = null;
    $cache ??= Configuracion::todas();
    return $cache[$clave] ?? $porDefecto;
}

function ip_cliente(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return substr(trim(explode(',', $ip)[0]), 0, 64);
}

/** Monto en bolivianos: Bs 1.250,50 */
function bs(mixed $monto): string
{
    return 'Bs ' . number_format((float) $monto, 2, ',', '.');
}

/** Cantidad sin decimales sobrantes: 25 · 2,5 */
function cantidad(mixed $n): string
{
    $n = (float) $n;
    return number_format($n, fmod($n, 1.0) == 0.0 ? 0 : 2, ',', '.');
}

/** Datos JSON enviados por formularios dinámicos (carrito, compra). */
function entrada_json(string $clave): array
{
    $v = json_decode((string) ($_POST[$clave] ?? ''), true);
    return is_array($v) ? $v : [];
}

// ---------------------------------------------------------------------
// Insignias (badges) reutilizables
// ---------------------------------------------------------------------
function badge(string $texto, string $clase = 'badge-off', bool $punto = true): string
{
    return '<span class="badge ' . $clase . '">' . ($punto ? '<span class="dot"></span>' : '') . e($texto) . '</span>';
}

function badge_estado(string $estado): string
{
    return $estado === 'ACTIVO' ? badge('Activo', 'badge-ok') : badge('Inactivo', 'badge-off');
}

const ESTADOS = [
    // Clases y reservas
    'PROGRAMADA' => ['Programada', 'badge-info'],
    'REALIZADA'  => ['Realizada', 'badge-ok'],
    'CANCELADA'  => ['Cancelada', 'badge-off'],
    'RESERVADA'  => ['Reservada', 'badge-accent'],
    'ASISTIO'    => ['Asistió', 'badge-ok'],
    'FALTO'      => ['Faltó', 'badge-warn'],
    // Compras, ventas y caja
    'REGISTRADA' => ['Registrada', 'badge-ok'],
    'VALIDA'     => ['Válida', 'badge-ok'],
    'ANULADA'    => ['Anulada', 'badge-danger'],
    'ABIERTA'    => ['Abierta', 'badge-ok'],
    'CERRADA'    => ['Cerrada', 'badge-off'],
    'PENDIENTE'  => ['Pendiente', 'badge-warn'],
    'APROBADO'   => ['Aprobado', 'badge-ok'],
    'RECHAZADO'  => ['Rechazado', 'badge-danger'],
];

function badge_estado_op(string $codigo): string
{
    [$nombre, $clase] = ESTADOS[$codigo] ?? [$codigo, 'badge-off'];
    return badge($nombre, $clase);
}

const NOMBRES_ROL = ['ADMINISTRADOR' => 'Administrador', 'ADMINISTRATIVO' => 'Administrativo', 'MAESTRA' => 'Maestra', 'ALUMNO' => 'Alumno', 'CLIENTE' => 'Cliente'];

function nombre_rol(?string $rol): string
{
    return NOMBRES_ROL[$rol] ?? mb_convert_case(str_replace('_', ' ', (string) $rol), MB_CASE_TITLE);
}

function badge_rol(?string $rol): string
{
    $clase = ['ADMINISTRADOR' => 'badge-accent', 'ADMINISTRATIVO' => 'badge-warn', 'MAESTRA' => 'badge-primary', 'ALUMNO' => 'badge-info', 'CLIENTE' => 'badge-ok'][$rol] ?? 'badge-off';
    return badge(nombre_rol($rol), $clase, false);
}

/** Insignia de stock: agotado · bajo el mínimo · suficiente */
function badge_stock(float $stock, float $minimo): string
{
    return match (true) {
        $stock <= 0       => badge('Agotado', 'badge-danger'),
        $stock <= $minimo => badge('Stock bajo', 'badge-warn'),
        default           => badge('Suficiente', 'badge-ok'),
    };
}

const NIVELES = ['INICIAL' => 'Inicial', 'INTERMEDIO' => 'Intermedio', 'AVANZADO' => 'Avanzado', 'TODOS' => 'Todos los niveles'];
const PUBLICOS = ['ADULTOS' => 'Adultos', 'NINOS' => 'Niños', 'TODOS' => 'Todo público'];

/** Icono SVG (subconjunto de Lucide) por nombre. */
function icono(string $nombre, int $tam = 18): string
{
    return Iconos::svg($nombre, $tam);
}
