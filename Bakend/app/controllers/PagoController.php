<?php
/**
 * CU21 · Pagar en línea: pasarela con QR bancario, tarjeta y PayPal (modo demostración).
 */

declare(strict_types=1);

final class PagoController extends Controlador
{
    public function pasarela(): void
    {
        $p = $this->pedidoPropio('pedido');
        if ($p['estado'] !== 'PENDIENTE_PAGO') {
            redirect(url('/mis-pedidos/ver', ['id' => $p['id']]));
        }
        $this->vista('Tienda/pasarela', [
            'pedido'  => $p,
            'detalle' => Pedido::detalle((int) $p['id']),
            'pagos'   => Pedido::pagos((int) $p['id']),
        ], 'tienda');
    }

    /** Tarjeta: se valida en el servidor y se pide la autorización (simulada). */
    public function tarjeta(): void
    {
        $p = $this->pedidoPropio('pedido_id');
        $volver = url('/pago', ['pedido' => $p['id'], 'metodo' => 'tarjeta']);
        $numero = entrada('numero');
        $vence = entrada('vence');
        $cvv = entrada('cvv');
        $titular = mb_strtoupper(entrada('titular'));
        $errores = Pasarela::validarTarjeta($numero, $vence, $cvv, $titular);
        if ($errores) {
            con_errores($errores, ['titular' => $titular, 'vence' => $vence]);   // nunca se reenvían número ni CVV
            flash('error', reset($errores));
            redirect($volver);
        }
        [$estado, $ref, $detalle, $marca, $ultimos] = Pasarela::cobrarTarjeta($numero);
        $this->registrar($p, 'TARJETA', $estado, $ref, ['marca' => $marca, 'ultimos4' => $ultimos, 'titular' => $titular, 'detalle' => $detalle], $volver);
    }

    /** QR: el cliente declara que pagó con su banco; el taller confirma el depósito. */
    public function qr(): void
    {
        $p = $this->pedidoPropio('pedido_id');
        $volver = url('/pago', ['pedido' => $p['id'], 'metodo' => 'qr']);
        $comprobante = entrada('comprobante');
        if ($comprobante !== '' && !preg_match('/^[A-Za-z0-9\-]{4,30}$/', $comprobante)) {
            flash('error', 'El número de transacción solo admite letras, números y guiones (4 a 30).');
            redirect($volver);
        }
        $this->registrar($p, 'QR', 'PENDIENTE', $comprobante !== '' ? 'QR-' . strtoupper($comprobante) : Pasarela::referencia('QR'),
            ['detalle' => 'El cliente indicó que pagó con QR'], $volver);
    }

    /** PayPal: pantalla de aprobación (sandbox simulado). */
    public function paypal(): void
    {
        $p = $this->pedidoPropio('pedido');
        if ($p['estado'] !== 'PENDIENTE_PAGO') {
            redirect(url('/mis-pedidos/ver', ['id' => $p['id']]));
        }
        $this->vista('Tienda/paypal', ['pedido' => $p], 'auth');
    }

    public function confirmarPaypal(): void
    {
        $p = $this->pedidoPropio('pedido_id');
        $cuenta = mb_strtolower(entrada('cuenta'));
        $volver = url('/pago/paypal', ['pedido' => $p['id']]);
        if (!filter_var($cuenta, FILTER_VALIDATE_EMAIL) || mb_strlen((string) ($_POST['clave'] ?? '')) < 4) {
            flash('error', 'Escriba el correo y la contraseña de su cuenta PayPal de prueba.');
            redirect($volver);
        }
        if (entrada('accion') === 'cancelar') {
            flash('info', 'Cancelaste el pago con PayPal. Puedes elegir otro método.');
            redirect(url('/pago', ['pedido' => $p['id']]));
        }
        $this->registrar($p, 'PAYPAL', 'APROBADO', Pasarela::referencia('PP'), ['cuenta' => $cuenta, 'detalle' => 'Aprobado en PayPal (sandbox simulado)'], $volver);
    }

    private function registrar(array $p, string $metodo, string $estado, string $ref, array $extra, string $volver): void
    {
        if (!$this->intentar(fn() => Pedido::registrarPago((int) $p['id'], $metodo, $estado, $ref, $extra))) {
            redirect($volver);
        }
        Bitacora::registrar('PAGO_' . $metodo, 'TIENDA', 'pedido', (int) $p['id'], "Pedido {$p['numero']}: $estado · $ref");
        if ($estado === 'RECHAZADO') {
            flash('error', 'Pago rechazado: ' . ($extra['detalle'] ?? 'intente con otro método') . '. Puede intentar de nuevo.');
            redirect($volver);
        }
        if ($estado === 'APROBADO') {
            $pedido = Pedido::buscar((int) $p['id']);
            $enviado = Pasarela::enviarComprobante($pedido, Pedido::detalle((int) $p['id']));
            flash('ok', '¡Pago aprobado! ' . ($enviado ? 'Te enviamos el comprobante a ' . $pedido['correo'] . '.' : 'Tu comprobante quedó guardado en "Mis pedidos".'));
        } else {
            flash('ok', 'Recibimos tu aviso de pago por QR. El taller confirmará el depósito y te avisaremos.');
        }
        redirect(url('/mis-pedidos/ver', ['id' => $p['id']]));
    }

    /** El pedido debe ser del usuario de la sesión. */
    private function pedidoPropio(string $campo): array
    {
        $u = Auth::requerir('tienda.comprar');
        $p = Pedido::buscar($this->id($campo)) ?? $this->noEncontrado();
        if ((int) $p['usuario_id'] !== (int) $u['id']) {
            $this->noEncontrado();
        }
        return $p;
    }
}
