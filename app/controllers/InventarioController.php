<?php
/**
 * CU13 · Gestionar materiales · CU14 · Gestionar proveedores · CU16 · Consumo, merma y alertas de stock.
 */

declare(strict_types=1);

final class InventarioController extends Controlador
{
    // ---------------------------------------------------------- Materiales

    public function materiales(): void
    {
        Auth::requerir('materiales.gestionar');
        $f = ['q' => entrada('q'), 'categoria' => entrada('categoria'), 'alerta' => entrada('alerta')];
        $this->vista('P3_Inventario/materiales/index', [
            'materiales' => Material::listar($f),
            'alertas'    => count(Material::alertas()),
            'categorias' => Configuracion::categorias('MATERIAL', true),
            'f'          => $f,
        ]);
    }

    public function formMaterial(): void
    {
        Auth::requerir('materiales.gestionar');
        $id = entrada_int('id');
        $this->vista('P3_Inventario/materiales/form', [
            'material'   => $id ? (Material::buscar($id) ?? $this->noEncontrado()) : null,
            'categorias' => Configuracion::categorias('MATERIAL', true),
            'unidades'   => Configuracion::unidades(true),
        ]);
    }

    public function guardarMaterial(): void
    {
        Auth::requerir('materiales.gestionar');
        $id = entrada_int('id');
        $d = ['nombre' => entrada('nombre'), 'descripcion' => entrada('descripcion'), 'categoria_id' => entrada('categoria_id'),
              'unidad_id' => entrada('unidad_id'), 'stock_minimo' => str_replace(',', '.', entrada('stock_minimo'))];
        $v = (new Validator($d))
            ->requerido('nombre', 'El nombre')->longitud('nombre', 2, 80)->max('descripcion', 300)
            ->requerido('categoria_id', 'La categoría')->en('categoria_id', array_map('strval', array_column(Configuracion::categorias('MATERIAL', true), 'id')), 'Categoría no válida.')
            ->requerido('unidad_id', 'La unidad')->en('unidad_id', array_map('strval', array_column(Configuracion::unidades(true), 'id')), 'Unidad no válida.')
            ->requerido('stock_minimo', 'El stock mínimo')->decimal('stock_minimo', 0, 100000);
        $this->validar($v, url('/materiales/form', ['id' => $id]), $d);
        if ($this->intentar(function () use ($id, $d, &$nuevo) {
            $nuevo = Material::guardar($id, $d);
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'INVENTARIO', 'material', $nuevo, "Material {$d['nombre']}");
        }, $d)) {
            flash('ok', $id ? 'Material actualizado.' : 'Material registrado con stock 0. Registre una compra para darle existencias.');
            redirect(url('/materiales/kardex', ['id' => $nuevo]));
        }
        redirect(url('/materiales/form', ['id' => $id]));
    }

    public function estadoMaterial(): void
    {
        Auth::requerir('materiales.gestionar');
        $m = Material::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($m['estado']);
        Material::cambiarEstado((int) $m['id'], $nuevo);
        Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'INVENTARIO', 'material', (int) $m['id'], "Material {$m['nombre']}");
        flash('ok', 'Material ' . ($nuevo === 'ACTIVO' ? 'activado.' : 'desactivado.'));
        volver('/materiales');
    }

    public function kardex(): void
    {
        Auth::requerir('materiales.gestionar');
        $m = Material::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P3_Inventario/materiales/kardex', ['material' => $m, 'movimientos' => Material::kardex((int) $m['id'])]);
    }

    /** CU16 · Registrar consumo, merma o ajuste (el trigger impide stock negativo). */
    public function movimiento(): void
    {
        Auth::requerir('materiales.gestionar');
        $m = Material::buscar($this->id()) ?? $this->noEncontrado();
        $d = ['tipo' => entrada('tipo'), 'cantidad' => str_replace(',', '.', entrada('cantidad')), 'motivo' => entrada('motivo')];
        $volver = url('/materiales/kardex', ['id' => $m['id']]);
        $v = (new Validator($d))
            ->requerido('tipo', 'El tipo')->en('tipo', array_keys(Material::TIPOS_SALIDA), 'Tipo de movimiento no válido.')
            ->requerido('cantidad', 'La cantidad')->decimal('cantidad', 0.01, 100000)
            ->requerido('motivo', 'El motivo')->longitud('motivo', 5, 255)
            ->si(in_array($d['tipo'], ['CONSUMO', 'MERMA', 'AJUSTE_MENOS'], true) && is_numeric($d['cantidad']) && (float) $d['cantidad'] > (float) $m['stock'],
                'cantidad', 'No hay suficiente stock: quedan ' . cantidad($m['stock']) . ' ' . $m['unidad'] . '.');
        $this->validar($v, $volver, $d);
        if ($this->intentar(fn() => Material::movimiento((int) $m['id'], $d['tipo'], $d['cantidad'], $d['motivo']), $d)) {
            Bitacora::registrar($d['tipo'], 'INVENTARIO', 'material', (int) $m['id'], "{$m['nombre']}: {$d['cantidad']} {$m['unidad']} · {$d['motivo']}");
            $m = Material::buscar((int) $m['id']);
            flash($m['stock'] <= $m['stock_minimo'] ? 'error' : 'ok',
                'Movimiento registrado. Stock actual: ' . cantidad($m['stock']) . " {$m['unidad']}"
                . ($m['stock'] <= $m['stock_minimo'] ? ' — ¡está en el mínimo o por debajo! Conviene comprar.' : '.'));
        }
        redirect($volver);
    }

    // --------------------------------------------------------- Proveedores

    public function proveedores(): void
    {
        Auth::requerir('proveedores.gestionar');
        $editar = entrada_int('editar');
        $this->vista('P3_Inventario/proveedores', ['proveedores' => Proveedor::todos(), 'editar' => $editar ? Proveedor::buscar($editar) : null]);
    }

    public function guardarProveedor(): void
    {
        Auth::requerir('proveedores.gestionar');
        $id = entrada_int('id');
        $d = ['nombre' => entrada('nombre'), 'nit' => entrada('nit'), 'contacto' => entrada('contacto'), 'telefono' => entrada('telefono'),
              'correo' => mb_strtolower(entrada('correo')), 'ciudad' => entrada('ciudad'), 'direccion' => entrada('direccion')];
        $v = (new Validator($d))
            ->requerido('nombre', 'El nombre')->longitud('nombre', 3, 100)
            ->regex('nit', '/^\d{5,15}$/', 'El NIT tiene de 5 a 15 dígitos.')
            ->max('contacto', 80)->telefono('telefono')->email('correo')->max('correo', 120)
            ->requerido('ciudad', 'La ciudad')->longitud('ciudad', 3, 40)->max('direccion', 200);
        $this->validar($v, url('/proveedores', ['editar' => $id]), $d);
        if ($this->intentar(function () use ($id, $d) {
            $n = Proveedor::guardar($id, $d);
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'INVENTARIO', 'proveedor', $n, "Proveedor {$d['nombre']}");
        }, $d)) {
            flash('ok', $id ? 'Proveedor actualizado.' : 'Proveedor registrado.');
            redirect('/proveedores');
        }
        redirect(url('/proveedores', ['editar' => $id]));
    }

    public function estadoProveedor(): void
    {
        Auth::requerir('proveedores.gestionar');
        $p = Proveedor::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($p['estado']);
        Proveedor::cambiarEstado((int) $p['id'], $nuevo);
        Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'INVENTARIO', 'proveedor', (int) $p['id'], "Proveedor {$p['nombre']}");
        flash('ok', 'Proveedor ' . ($nuevo === 'ACTIVO' ? 'activado.' : 'desactivado.'));
        redirect('/proveedores');
    }
}
