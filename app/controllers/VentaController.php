<?php
/**
 * CU18 · Registrar venta en caja (nota de venta, anulación con motivo y clientes)
 * CU19 · Abrir y cerrar caja con arqueo.
 */

declare(strict_types=1);

final class VentaController extends Controlador
{
    // ----------------------------------------------------- Punto de venta

    public function nueva(): void
    {
        Auth::requerir('ventas.registrar');
        $this->vista('P4_Ventas/ventas/nueva', [
            'caja'     => Venta::cajaAbierta(),
            'piezas'   => Pieza::listar(['venta' => 1]),
            'clientes' => Cliente::activos(),
            'metodos'  => Configuracion::metodosPago(true),
        ]);
    }

    public function guardar(): void
    {
        Auth::requerir('ventas.registrar');
        $items = [];
        foreach (entrada_json('carrito') as $it) {
            $pid = filter_var($it['pieza_id'] ?? null, FILTER_VALIDATE_INT);
            $cant = filter_var($it['cantidad'] ?? null, FILTER_VALIDATE_INT);
            if ($pid && $cant) {
                $items[] = ['pieza_id' => $pid, 'cantidad' => $cant];
            }
        }
        $d = ['cliente_id' => entrada('cliente_id'), 'metodo_pago_id' => entrada('metodo_pago_id'), 'recibido' => str_replace(',', '.', entrada('recibido'))];
        $v = (new Validator($d))
            ->requerido('cliente_id', 'El cliente')->entero('cliente_id', 1)
            ->requerido('metodo_pago_id', 'El método de pago')->en('metodo_pago_id', array_map('strval', array_column(Configuracion::metodosPago(true), 'id')), 'Método de pago no válido.')
            ->decimal('recibido', 0, 1000000)
            ->si(!$items, 'carrito', 'Agregue al menos una pieza a la venta.');
        foreach ($items as $it) {
            $v->si($it['cantidad'] < 1 || $it['cantidad'] > 100, 'carrito', 'Cantidad no válida en la venta.');
        }
        $this->validar($v, '/ventas/nueva', $d);

        if ($this->intentar(function () use ($d, $items, &$id) {
            $id = Venta::registrar((int) $d['cliente_id'], (int) $d['metodo_pago_id'], $d['recibido'] !== '' ? $d['recibido'] : null, $items);
            $venta = Venta::buscar($id);
            Bitacora::registrar('VENDER', 'VENTAS', 'venta', $id, "Venta {$venta['numero']} por " . bs($venta['total']) . " ({$venta['metodo']})");
        }, $d)) {
            flash('ok', 'Venta registrada. Puede imprimir la nota de venta.');
            redirect(url('/ventas/ver', ['id' => $id]));
        }
        redirect('/ventas/nueva');
    }

    public function index(): void
    {
        Auth::requerir('ventas.registrar', 'ventas.anular');
        $f = ['q' => entrada('q'), 'estado' => entrada('estado'), 'desde' => entrada('desde', date('Y-m-01')), 'hasta' => entrada('hasta', hoy())];
        foreach (['desde', 'hasta'] as $k) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) {
                $f[$k] = '';
            }
        }
        $pag = new Paginador(Venta::contar($f));
        $this->vista('P4_Ventas/ventas/index', [
            'ventas'  => Venta::listar($f, $pag->porPagina, $pag->offset),
            'totales' => Venta::totales($f),
            'f'       => $f,
            'pag'     => $pag,
        ]);
    }

    /** Nota de venta (comprobante imprimible). */
    public function ver(): void
    {
        Auth::requerir('ventas.registrar', 'ventas.anular');
        $v = Venta::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P4_Ventas/ventas/ver', ['venta' => $v, 'detalle' => Venta::detalle((int) $v['id'])]);
    }

    public function anular(): void
    {
        Auth::requerir('ventas.anular');
        $v = Venta::buscar($this->id()) ?? $this->noEncontrado();
        $motivo = entrada('motivo');
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 255) {
            flash('error', 'Escriba el motivo de la anulación (5 a 255 caracteres).');
            redirect(url('/ventas/ver', ['id' => $v['id']]));
        }
        if ($this->intentar(fn() => Venta::anular((int) $v['id'], $motivo))) {
            flash('ok', "Venta {$v['numero']} anulada: las piezas volvieron al stock.");
        }
        redirect(url('/ventas/ver', ['id' => $v['id']]));
    }

    // ---------------------------------------------------------------- Caja

    public function caja(): void
    {
        Auth::requerir('caja.gestionar');
        $caja = Venta::cajaAbierta();
        $this->vista('P4_Ventas/caja', [
            'caja'    => $caja,
            'ventas'  => $caja ? Venta::listar(['caja' => $caja['id']], 50, 0) : [],
            'cajas'   => Venta::cajas(),
        ]);
    }

    public function abrirCaja(): void
    {
        Auth::requerir('caja.gestionar');
        $monto = str_replace(',', '.', entrada('monto_inicial'));
        $v = (new Validator(['monto_inicial' => $monto]))->requerido('monto_inicial', 'El monto inicial')->decimal('monto_inicial', 0, 100000);
        $this->validar($v, '/caja');
        if ($this->intentar(function () use ($monto) {
            $id = Venta::abrirCaja($monto);
            Bitacora::registrar('ABRIR_CAJA', 'VENTAS', 'caja', $id, 'Monto inicial ' . bs($monto));
        })) {
            flash('ok', 'Caja abierta. Ya puede registrar ventas.');
        }
        redirect('/caja');
    }

    public function cerrarCaja(): void
    {
        Auth::requerir('caja.gestionar');
        $caja = Venta::cajaAbierta();
        if (!$caja) {
            flash('error', 'No hay una caja abierta.');
            redirect('/caja');
        }
        $d = ['monto_declarado' => str_replace(',', '.', entrada('monto_declarado')), 'observacion' => entrada('observacion')];
        $v = (new Validator($d))->requerido('monto_declarado', 'El efectivo contado')->decimal('monto_declarado', 0, 1000000)->max('observacion', 300);
        $this->validar($v, '/caja', $d);
        if ($this->intentar(fn() => Venta::cerrarCaja((int) $caja['id'], $d['monto_declarado'], $d['observacion']), $d)) {
            $c = Database::fila('select diferencia from caja where id = :id', ['id' => $caja['id']]);
            Bitacora::registrar('CERRAR_CAJA', 'VENTAS', 'caja', (int) $caja['id'], 'Declarado ' . bs($d['monto_declarado']) . ' · diferencia ' . bs($c['diferencia']));
            $dif = (float) $c['diferencia'];
            flash($dif == 0.0 ? 'ok' : 'info', 'Caja cerrada. ' . ($dif == 0.0 ? 'El arqueo cuadra exactamente.' : ($dif > 0 ? 'Sobran ' : 'Faltan ') . bs(abs($dif)) . '.'));
        }
        redirect('/caja');
    }

    // ------------------------------------------------------------ Clientes

    public function clientes(): void
    {
        Auth::requerir('clientes.gestionar');
        $editar = entrada_int('editar');
        $this->vista('P4_Ventas/clientes', [
            'clientes' => Cliente::listar(entrada('q')),
            'editar'   => $editar ? Cliente::buscar($editar) : null,
            'q'        => entrada('q'),
        ]);
    }

    public function guardarCliente(): void
    {
        Auth::requerir('clientes.gestionar', 'ventas.registrar');
        $id = entrada_int('id');
        $d = ['nombre' => entrada('nombre'), 'ci_nit' => entrada('ci_nit'), 'telefono' => entrada('telefono'), 'correo' => mb_strtolower(entrada('correo'))];
        $v = (new Validator($d))
            ->requerido('nombre', 'El nombre')->longitud('nombre', 3, 120)
            ->regex('ci_nit', '/^\d{4,15}(-[0-9A-Z]{1,3})?$/', 'CI/NIT no válido.')
            ->telefono('telefono')->email('correo')->max('correo', 120);
        $desdeVenta = entrada('origen') === 'venta';
        $this->validar($v, $desdeVenta ? '/ventas/nueva' : url('/clientes', ['editar' => $id]), $d);
        if ($this->intentar(function () use ($id, $d, &$nuevo) {
            $nuevo = Cliente::guardar($id, $d);
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'VENTAS', 'cliente', $nuevo, "Cliente {$d['nombre']}");
        }, $d)) {
            flash('ok', $id ? 'Cliente actualizado.' : 'Cliente registrado.');
            if ($desdeVenta) {
                $_SESSION['cliente_nuevo'] = $nuevo;
            }
        }
        redirect($desdeVenta ? '/ventas/nueva' : '/clientes');
    }

    public function estadoCliente(): void
    {
        Auth::requerir('clientes.gestionar');
        $c = Cliente::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($c['estado']);
        if ($this->intentar(fn() => Cliente::cambiarEstado((int) $c['id'], $nuevo))) {
            Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'VENTAS', 'cliente', (int) $c['id'], "Cliente {$c['nombre']}");
            flash('ok', 'Cliente ' . ($nuevo === 'ACTIVO' ? 'activado.' : 'desactivado.'));
        }
        redirect('/clientes');
    }
}
