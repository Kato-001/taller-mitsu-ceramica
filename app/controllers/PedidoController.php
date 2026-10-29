<?php
/**
 * CU22 · Gestionar pedidos en línea: confirmar pagos por QR, entregar y cancelar.
 */

declare(strict_types=1);

final class PedidoController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('pedidos.gestionar');
        $f = ['estado' => entrada('estado'), 'q' => entrada('q')];
        if ($f['estado'] !== '' && !isset(Pedido::ESTADOS[$f['estado']])) {
            $f['estado'] = '';
        }
        $pag = new Paginador(Pedido::contar($f));
        $this->vista('P4_Ventas/pedidos/index', [
            'pedidos' => Pedido::listar($f, $pag->porPagina, $pag->offset),
            'conteo'  => Pedido::porEstado(),
            'f'       => $f,
            'pag'     => $pag,
        ]);
    }

    public function ver(): void
    {
        Auth::requerir('pedidos.gestionar');
        $p = Pedido::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P4_Ventas/pedidos/ver', ['pedido' => $p, 'detalle' => Pedido::detalle((int) $p['id']), 'pagos' => Pedido::pagos((int) $p['id'])]);
    }

    public function verificarQr(): void
    {
        Auth::requerir('pedidos.gestionar');
        $pagoId = $this->id('pago_id');
        $aprobar = entrada('decision') === 'aprobar';
        $p = Pedido::buscar($this->id('pedido_id')) ?? $this->noEncontrado();
        $motivo = entrada('motivo');
        if (!$aprobar && mb_strlen($motivo) < 5) {
            flash('error', 'Escriba por qué se rechaza el pago (mínimo 5 caracteres).');
            redirect(url('/pedidos/ver', ['id' => $p['id']]));
        }
        if ($this->intentar(fn() => Pedido::verificarQr($pagoId, $aprobar, $aprobar ? 'Depósito confirmado por el taller' : $motivo))) {
            Bitacora::registrar($aprobar ? 'APROBAR_QR' : 'RECHAZAR_QR', 'TIENDA', 'pago', $pagoId, "Pedido {$p['numero']}" . ($aprobar ? '' : ": $motivo"));
            if ($aprobar) {
                $enviado = Pasarela::enviarComprobante(Pedido::buscar((int) $p['id']), Pedido::detalle((int) $p['id']));
                flash('ok', 'Pago confirmado: el pedido quedó pagado.' . ($enviado ? ' Se envió el comprobante al cliente.' : ''));
            } else {
                flash('ok', 'Pago rechazado: el cliente tiene 30 minutos para volver a pagar.');
            }
        }
        redirect(url('/pedidos/ver', ['id' => $p['id']]));
    }

    public function entregar(): void
    {
        Auth::requerir('pedidos.gestionar');
        $p = Pedido::buscar($this->id()) ?? $this->noEncontrado();
        if ($this->intentar(fn() => Pedido::entregar((int) $p['id']))) {
            Bitacora::registrar('ENTREGAR', 'TIENDA', 'pedido', (int) $p['id'], "Pedido {$p['numero']} entregado");
            flash('ok', "Pedido {$p['numero']} marcado como entregado.");
        }
        redirect(url('/pedidos/ver', ['id' => $p['id']]));
    }

    public function cancelar(): void
    {
        Auth::requerir('pedidos.gestionar');
        $p = Pedido::buscar($this->id()) ?? $this->noEncontrado();
        $motivo = entrada('motivo');
        if ($this->intentar(fn() => Pedido::cancelar((int) $p['id'], $motivo))) {
            Bitacora::registrar('CANCELAR', 'TIENDA', 'pedido', (int) $p['id'], "Pedido {$p['numero']}: $motivo");
            flash('ok', "Pedido {$p['numero']} cancelado: las piezas volvieron al stock." . ($p['estado'] === 'PAGADO' ? ' Recuerde devolver el dinero al cliente.' : ''));
        }
        redirect(url('/pedidos/ver', ['id' => $p['id']]));
    }
}
