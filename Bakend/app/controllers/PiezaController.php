<?php
/**
 * CU17 · Gestionar piezas de cerámica (con foto, precio, producción y kardex).
 */

declare(strict_types=1);

final class PiezaController extends Controlador
{
    private const PREFIJOS = ['Tazas y vasos' => 'TZ', 'Vasijas y jarras' => 'VS', 'Vajilla' => 'VJ', 'Decorativas' => 'DC'];
    private const MAX_FOTO = 2 * 1024 * 1024;

    public function index(): void
    {
        Auth::requerir('piezas.gestionar', 'ventas.registrar');
        $f = ['q' => entrada('q'), 'categoria' => entrada('categoria'), 'alerta' => entrada('alerta')];
        $this->vista('P4_Ventas/piezas/index', [
            'piezas'     => Pieza::listar($f),
            'categorias' => Configuracion::categorias('PIEZA', true),
            'f'          => $f,
        ]);
    }

    public function form(): void
    {
        Auth::requerir('piezas.gestionar');
        $id = entrada_int('id');
        $this->vista('P4_Ventas/piezas/form', [
            'pieza'      => $id ? (Pieza::buscar($id) ?? $this->noEncontrado()) : null,
            'categorias' => Configuracion::categorias('PIEZA', true),
        ]);
    }

    public function guardar(): void
    {
        Auth::requerir('piezas.gestionar');
        $id = entrada_int('id');
        $actual = $id ? (Pieza::buscar($id) ?? $this->noEncontrado()) : null;
        $d = ['nombre' => entrada('nombre'), 'descripcion' => entrada('descripcion'), 'categoria_id' => entrada('categoria_id'),
              'precio' => str_replace(',', '.', entrada('precio')), 'stock_minimo' => entrada('stock_minimo')];
        $categorias = Configuracion::categorias('PIEZA', true);
        $v = (new Validator($d))
            ->requerido('nombre', 'El nombre')->longitud('nombre', 3, 100)->max('descripcion', 500)
            ->requerido('categoria_id', 'La categoría')->en('categoria_id', array_map('strval', array_column($categorias, 'id')), 'Categoría no válida.')
            ->requerido('precio', 'El precio')->decimal('precio', 1, 50000)
            ->requerido('stock_minimo', 'El stock mínimo')->entero('stock_minimo', 0, 1000);
        $foto = $this->validarFoto($v);
        $volver = url('/piezas/form', ['id' => $id]);
        $this->validar($v, $volver, $d);

        if (!$id) {
            $cat = array_values(array_filter($categorias, fn($c) => (string) $c['id'] === $d['categoria_id']))[0]['nombre'] ?? '';
            $d['codigo'] = Pieza::siguienteCodigo(self::PREFIJOS[$cat] ?? 'PZ');
        }
        if ($this->intentar(function () use ($id, $d, $foto, $actual, &$nuevo) {
            $nuevo = Pieza::guardar($id, $d);
            if ($foto) {
                Pieza::guardarFoto($nuevo, $foto['mime'], $foto['binario']);
            }
            $detalle = $id && (float) $actual['precio'] !== (float) $d['precio'] ? " · precio {$actual['precio']} → {$d['precio']}" : '';
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'VENTAS', 'pieza', $nuevo, "Pieza {$d['nombre']}$detalle");
        }, $d)) {
            flash('ok', $id ? 'Pieza actualizada.' : "Pieza {$d['codigo']} registrada. Registre su producción para tener stock.");
            redirect(url('/piezas/kardex', ['id' => $nuevo]));
        }
        redirect($volver);
    }

    public function estado(): void
    {
        Auth::requerir('piezas.gestionar');
        $p = Pieza::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($p['estado']);
        Pieza::cambiarEstado((int) $p['id'], $nuevo);
        Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'VENTAS', 'pieza', (int) $p['id'], "Pieza {$p['codigo']}");
        flash('ok', $nuevo === 'ACTIVO' ? 'Pieza a la venta.' : 'Pieza retirada de la venta.');
        volver('/piezas');
    }

    public function kardex(): void
    {
        Auth::requerir('piezas.gestionar');
        $p = Pieza::buscar($this->id()) ?? $this->noEncontrado();
        $this->vista('P4_Ventas/piezas/kardex', [
            'pieza'       => $p,
            'movimientos' => Pieza::kardex((int) $p['id']),
            'precios'     => Pieza::historialPrecios((int) $p['id']),
        ]);
    }

    public function movimiento(): void
    {
        Auth::requerir('piezas.gestionar');
        $p = Pieza::buscar($this->id()) ?? $this->noEncontrado();
        $d = ['tipo' => entrada('tipo'), 'cantidad' => entrada('cantidad'), 'motivo' => entrada('motivo')];
        $volver = url('/piezas/kardex', ['id' => $p['id']]);
        $v = (new Validator($d))
            ->requerido('tipo', 'El tipo')->en('tipo', array_keys(Pieza::TIPOS_MOVIMIENTO), 'Tipo no válido.')
            ->requerido('cantidad', 'La cantidad')->entero('cantidad', 1, 1000)
            ->requerido('motivo', 'El motivo')->longitud('motivo', 5, 255)
            ->si($d['tipo'] === 'AJUSTE_MENOS' && (int) $d['cantidad'] > (int) $p['stock'], 'cantidad', "Solo hay {$p['stock']} unidad(es).");
        $this->validar($v, $volver, $d);
        if ($this->intentar(fn() => Pieza::movimiento((int) $p['id'], $d['tipo'], (int) $d['cantidad'], $d['motivo']), $d)) {
            Bitacora::registrar($d['tipo'], 'VENTAS', 'pieza', (int) $p['id'], "{$p['codigo']}: {$d['cantidad']} u. · {$d['motivo']}");
            flash('ok', 'Movimiento registrado.');
        }
        redirect($volver);
    }

    /** Entrega la foto guardada en la base de datos (con caché del navegador). */
    public function foto(): void
    {
        $f = Pieza::leerFoto($this->id()) ?? $this->noEncontrado();
        header('Content-Type: ' . $f['mime']);
        header('Cache-Control: public, max-age=604800');
        header('Content-Length: ' . strlen($f['contenido']));
        echo $f['contenido'];
    }

    /** Valida la foto subida: tipo real de imagen (no la extensión) y tamaño. */
    private function validarFoto(Validator $v): ?array
    {
        $a = $_FILES['foto'] ?? null;
        if (!$a || ($a['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($a['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($a['tmp_name'])) {
            $v->si(true, 'foto', 'No se pudo subir la foto. Intente con una imagen más liviana.');
            return null;
        }
        $info = @getimagesize($a['tmp_name']);
        $mime = $info['mime'] ?? '';
        $v->si(!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 'foto', 'La foto debe ser JPG, PNG o WEBP.')
          ->si($a['size'] > self::MAX_FOTO, 'foto', 'La foto no debe pasar de 2 MB.');
        return $v->falla() ? null : ['mime' => $mime, 'binario' => (string) file_get_contents($a['tmp_name'])];
    }
}
