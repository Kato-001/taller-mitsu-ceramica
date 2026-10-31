<?php
/**
 * CU20 · Comprar en la tienda en línea.
 * El catálogo y el carrito son públicos; para pagar hay que ingresar con una cuenta
 * (correo y contraseña, o Google), así el comprobante llega a su correo.
 */

declare(strict_types=1);

final class TiendaController extends Controlador
{
    private const MAX_POR_PIEZA = 20;

    // ------------------------------------------------------- Páginas públicas

    public function inicio(): void
    {
        $this->vista('Tienda/inicio', [
            'piezas'   => array_slice(Pieza::listar(['venta' => 1]), 0, 8),
            'talleres' => Taller::todos(true),
        ], 'tienda');
    }

    public function catalogo(): void
    {
        $f = ['q' => entrada('q'), 'categoria' => entrada('categoria'), 'venta' => 1];
        $this->vista('Tienda/catalogo', [
            'piezas'     => Pieza::listar($f),
            'categorias' => Configuracion::categorias('PIEZA', true),
            'f'          => $f,
        ], 'tienda');
    }

    public function pieza(): void
    {
        $p = Pieza::buscar($this->id());
        if (!$p || $p['estado'] !== 'ACTIVO') {
            $this->noEncontrado();
        }
        $this->vista('Tienda/pieza', ['pieza' => $p, 'otras' => array_slice(array_filter(Pieza::listar(['venta' => 1]), fn($x) => $x['id'] !== $p['id']), 0, 4)], 'tienda');
    }

    // ------------------------------------------------------------- Carrito

    /** @return array<int,int> pieza_id => cantidad */
    public static function carrito(): array
    {
        return array_filter(array_map('intval', (array) ($_SESSION['carrito'] ?? [])), fn($c) => $c > 0);
    }

    /** Piezas del carrito con precio y stock actuales de la base de datos. */
    public static function lineas(): array
    {
        $lineas = [];
        foreach (self::carrito() as $id => $cant) {
            $p = Pieza::buscar((int) $id);
            if ($p && $p['estado'] === 'ACTIVO') {
                $lineas[] = ['pieza' => $p, 'cantidad' => min($cant, (int) $p['stock']), 'pedida' => $cant,
                             'subtotal' => (float) $p['precio'] * min($cant, (int) $p['stock'])];
            }
        }
        return $lineas;
    }

    public function verCarrito(): void
    {
        $this->vista('Tienda/carrito', ['lineas' => self::lineas()], 'tienda');
    }

    public function agregar(): void
    {
        $p = Pieza::buscar($this->id('pieza_id'));
        if (!$p || $p['estado'] !== 'ACTIVO') {
            $this->noEncontrado();
        }
        $cant = max(1, min(self::MAX_POR_PIEZA, (int) entrada_int('cantidad') ?: 1));
        $nueva = min((int) $p['stock'], ($_SESSION['carrito'][$p['id']] ?? 0) + $cant);
        if ($nueva <= 0) {
            flash('error', "\"{$p['nombre']}\" está agotada por ahora.");
        } else {
            $_SESSION['carrito'][$p['id']] = $nueva;
            flash('ok', "Agregaste \"{$p['nombre']}\" al carrito.");
        }
        if (es_ajax()) {
            json_response(['ok' => $nueva > 0, 'total' => array_sum(self::carrito())]);
        }
        redirect(entrada('volver') === 'carrito' ? '/tienda/carrito' : volverSeguro('/tienda'));
    }

    public function actualizar(): void
    {
        foreach ((array) ($_POST['cantidad'] ?? []) as $id => $cant) {
            $id = (int) $id;
            $cant = max(0, min(self::MAX_POR_PIEZA, (int) $cant));
            if ($cant === 0) {
                unset($_SESSION['carrito'][$id]);
            } else {
                $_SESSION['carrito'][$id] = $cant;
            }
        }
        if (($q = entrada_int('quitar')) !== null) {
            unset($_SESSION['carrito'][$q]);
        }
        redirect('/tienda/carrito');
    }

    // ------------------------------------------------------------ Checkout

    public function checkout(): void
    {
        $u = $this->exigirCliente('/tienda/checkout');
        $lineas = self::lineas();
        if (!$lineas) {
            flash('info', 'Tu carrito está vacío.');
            redirect('/tienda');
        }
        $this->vista('Tienda/checkout', ['lineas' => $lineas, 'u' => $u], 'tienda');
    }

    public function confirmar(): void
    {
        $u = $this->exigirCliente('/tienda/checkout');
        $d = ['entrega' => entrada('entrega'), 'direccion' => entrada('direccion'), 'telefono' => entrada('telefono'), 'nota' => entrada('nota')];
        $items = array_map(fn($l) => ['pieza_id' => (int) $l['pieza']['id'], 'cantidad' => (int) $l['cantidad']],
            array_filter(self::lineas(), fn($l) => $l['cantidad'] > 0));
        $v = (new Validator($d))
            ->requerido('entrega', 'La forma de entrega')->en('entrega', ['RETIRO', 'ENVIO'])
            ->si($d['entrega'] === 'ENVIO' && mb_strlen($d['direccion']) < 8, 'direccion', 'Escribe la dirección de envío (mínimo 8 caracteres).')
            ->max('direccion', 200)
            ->requerido('telefono', 'El teléfono')->telefono('telefono')->max('nota', 300)
            ->si(!$items, 'carrito', 'Tu carrito está vacío o las piezas se agotaron.');
        $this->validar($v, '/tienda/checkout', $d);

        if ($this->intentar(function () use ($u, $d, $items, &$id) {
            $id = Pedido::crear($u, $d, $items);
            $p = Pedido::buscar($id);
            Bitacora::registrar('PEDIDO_WEB', 'TIENDA', 'pedido', $id, "Pedido {$p['numero']} por " . bs($p['total']));
        }, $d)) {
            unset($_SESSION['carrito']);
            flash('ok', 'Pedido creado. Tus piezas quedan reservadas 30 minutos mientras pagas.');
            redirect(url('/pago', ['pedido' => $id]));
        }
        redirect('/tienda/checkout');
    }

    // --------------------------------------------------- Cuenta del cliente

    public function registro(): void
    {
        if (Auth::usuario()) {
            redirect('/tienda');
        }
        $this->vista('Tienda/registro', ['google' => GoogleController::configurado()], 'tienda');
    }

    public function registrar(): void
    {
        $d = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
        $d['correo'] = mb_strtolower($d['correo'] ?? '');
        $v = UsuarioController::validadorPersona($d)
            ->password('password')->coincide('password_confirmacion', 'password', 'Las contraseñas no coinciden.')
            ->si(Usuario::existeCorreo($d['correo']), 'correo', 'Ese correo ya tiene una cuenta. Ingresa con él.')
            ->si(empty($d['acepta']), 'acepta', 'Debes aceptar el uso de tus datos para procesar tus compras.');
        $this->validar($v, '/tienda/registro', $d);
        $ok = $this->intentar(function () use ($d) {
            $id = Usuario::crear($d + ['rol_id' => Rol::idPorNombre('CLIENTE')]);
            Database::query('insert into cliente (nombre, telefono, correo, usuario_id) values (:n, :t, :c, :u)',
                ['n' => "{$d['nombre']} {$d['apellido']}", 't' => $d['telefono'] ?: null, 'c' => $d['correo'], 'u' => $id]);
            Bitacora::registrar('REGISTRO', 'TIENDA', 'usuario', $id, 'Cliente se registró en la tienda: ' . $d['correo'], null);
        }, $d);
        if (!$ok) {
            redirect('/tienda/registro');
        }
        Auth::intentar($d['correo'], $d['password']);
        flash('ok', '¡Bienvenido! Tu cuenta está lista.');
        redirect(self::destino('/tienda'));
    }

    public function misPedidos(): void
    {
        $u = Auth::requerir('tienda.comprar');
        $this->vista('Tienda/mis_pedidos', ['pedidos' => Pedido::delUsuario((int) $u['id'])], 'tienda');
    }

    public function verPedido(): void
    {
        $u = Auth::requerir('tienda.comprar', 'pedidos.gestionar');
        $p = Pedido::buscar($this->id()) ?? $this->noEncontrado();
        if ((int) $p['usuario_id'] !== (int) $u['id'] && !Auth::puede('pedidos.gestionar')) {
            $this->noEncontrado();
        }
        $this->vista('Tienda/comprobante', ['pedido' => $p, 'detalle' => Pedido::detalle((int) $p['id']), 'pagos' => Pedido::pagos((int) $p['id'])], 'tienda');
    }

    public function cancelarPedido(): void
    {
        $u = Auth::requerir('tienda.comprar');
        $p = Pedido::buscar($this->id()) ?? $this->noEncontrado();
        if ((int) $p['usuario_id'] !== (int) $u['id']) {
            $this->noEncontrado();
        }
        if ($p['estado'] !== 'PENDIENTE_PAGO') {
            flash('error', 'Solo puedes cancelar pedidos que todavía no pagaste. Para otro caso, comunícate con el taller.');
            redirect(url('/mis-pedidos/ver', ['id' => $p['id']]));
        }
        if ($this->intentar(fn() => Pedido::cancelar((int) $p['id'], 'Cancelado por el cliente'))) {
            flash('ok', "Pedido {$p['numero']} cancelado. Las piezas volvieron a la tienda.");
        }
        redirect('/mis-pedidos');
    }

    // ------------------------------------------------------------ Ayudas

    /** Exige una cuenta que pueda comprar; si no hay sesión, guarda a dónde volver. */
    private function exigirCliente(string $volver): array
    {
        $u = Auth::usuario();
        if (!$u) {
            $_SESSION['volver'] = $volver;
            flash('info', 'Ingresa o crea tu cuenta para terminar la compra. Te enviaremos el comprobante a tu correo.');
            redirect('/tienda/ingresar');
        }
        return Auth::requerir('tienda.comprar');
    }

    /** Ruta guardada antes de pedir el ingreso (solo rutas internas). */
    public static function destino(string $porDefecto): string
    {
        $v = (string) ($_SESSION['volver'] ?? '');
        unset($_SESSION['volver']);
        return str_starts_with($v, '/') && !str_starts_with($v, '//') ? $v : $porDefecto;
    }
}

