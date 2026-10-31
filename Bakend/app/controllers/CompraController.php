<?php
/**
 * CU15 · Registrar y anular compras a proveedores (suben el stock de materiales).
 */

declare(strict_types=1);

final class CompraController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('compras.gestionar');
        $f = ['proveedor' => entrada('proveedor'), 'estado' => entrada('estado'), 'desde' => entrada('desde'), 'hasta' => entrada('hasta')];
        $pag = new Paginador(Compra::contar($f));
        $this->vista('P3_Inventario/compras/index', [
            'compras'     => Compra::listar($f, $pag->porPagina, $pag->offset),
            'proveedores' => Proveedor::todos(),
            'totalMes'    => Compra::totalMes(),
            'f'           => $f,
            'pag'         => $pag,
        ]);
    }

    public function nueva(): void
    {
        Auth::requerir('compras.gestionar');
        $this->vista('P3_Inventario/compras/nueva', [
            'proveedores' => Proveedor::todos(true),
            'materiales'  => Material::activos(),
            'material'    => entrada_int('material'),
        ]);
    }

    public function guardar(): void
    {
        Auth::requerir('compras.gestionar');
        $d = ['proveedor_id' => entrada('proveedor_id'), 'fecha' => entrada('fecha'), 'observacion' => entrada('observacion')];
        $items = [];
        foreach ((array) ($_POST['items'] ?? []) as $it) {
            if (!is_array($it) || ($it['material_id'] ?? '') === '') {
                continue;
            }
            $items[] = ['material_id' => (int) $it['material_id'],
                        'cantidad' => str_replace(',', '.', trim((string) ($it['cantidad'] ?? ''))),
                        'costo' => str_replace(',', '.', trim((string) ($it['costo'] ?? '')))];
        }
        $ids = array_column($items, 'material_id');
        $v = (new Validator($d))
            ->requerido('proveedor_id', 'El proveedor')->en('proveedor_id', array_map('strval', array_column(Proveedor::todos(true), 'id')), 'Proveedor no válido.')
            ->requerido('fecha', 'La fecha')->fecha('fecha', date('Y-m-d', strtotime('-60 days')), hoy())
            ->max('observacion', 300)
            ->si(!$items, 'items', 'Agregue al menos un material.')
            ->si(count($ids) !== count(array_unique($ids)), 'items', 'Un material está repetido: júntelo en una sola fila.');
        foreach ($items as $i => $it) {
            $v->si(!is_numeric($it['cantidad']) || (float) $it['cantidad'] <= 0 || (float) $it['cantidad'] > 100000, "items.$i", 'Fila ' . ($i + 1) . ': cantidad no válida.')
              ->si(!is_numeric($it['costo']) || (float) $it['costo'] < 0 || (float) $it['costo'] > 100000, "items.$i", 'Fila ' . ($i + 1) . ': costo no válido.');
        }
        $this->validar($v, '/compras/nueva', $d + ['items' => $items]);

        if ($this->intentar(function () use ($d, $items, &$id) {
            $id = Compra::registrar((int) $d['proveedor_id'], $d['fecha'], $d['observacion'], $items);
            $c = Compra::buscar($id);
            Bitacora::registrar('CREAR', 'INVENTARIO', 'compra', $id, "Compra {$c['numero']} a {$c['proveedor']} por " . bs($c['total']));
        }, $d + ['items' => $items])) {
            flash('ok', 'Compra registrada: el stock de los materiales aumentó.');
            redirect(url('/compras/ver', ['id' => $id]));
        }
        redirect('/compras/nueva');
    }

    public function ver(): void
    {
        Auth::requerir('compras.gestionar');
        $c = Compra::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P3_Inventario/compras/ver', ['compra' => $c, 'detalle' => Compra::detalle((int) $c['id'])]);
    }

    public function anular(): void
    {
        Auth::requerir('compras.gestionar');
        $c = Compra::buscar($this->id()) ?? $this->noEncontrado();
        $motivo = entrada('motivo');
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 255) {
            flash('error', 'Escriba el motivo de la anulación (5 a 255 caracteres).');
            redirect(url('/compras/ver', ['id' => $c['id']]));
        }
        if ($this->intentar(fn() => Compra::anular((int) $c['id'], $motivo))) {
            flash('ok', "Compra {$c['numero']} anulada: se descontó lo que había ingresado.");
        }
        redirect(url('/compras/ver', ['id' => $c['id']]));
    }
}
