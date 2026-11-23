<?php
/**
 * CU26 · Calificar el servicio (cliente o alumno) y responder (taller).
 */

declare(strict_types=1);

final class CalificacionController extends Controlador
{
    public function formulario(): void
    {
        $u = Auth::requerir('tienda.comprar', 'portal.alumno');
        $tipo = entrada('tipo');
        $s = isset(Calificacion::TIPOS[$tipo]) ? Calificacion::servicio($tipo, $this->id(), (int) $u['id']) : null;
        if (!$s) {
            $this->noEncontrado();
        }
        if ($s['calificacion_id']) {
            flash('info', 'Ya calificaste este servicio. ¡Gracias!');
            redirect(self::regreso($tipo));
        }
        $this->vista('P5_Reportes/calificar', ['tipo' => $tipo, 'servicio' => $s], 'tienda');
    }

    public function guardar(): void
    {
        $u = Auth::requerir('tienda.comprar', 'portal.alumno');
        $tipo = entrada('tipo');
        $id = $this->id();
        $s = isset(Calificacion::TIPOS[$tipo]) ? Calificacion::servicio($tipo, $id, (int) $u['id']) : null;
        if (!$s) {
            $this->noEncontrado();
        }
        $d = ['puntaje' => entrada('puntaje'), 'comentario' => entrada('comentario')];
        $v = (new Validator($d))
            ->requerido('puntaje', 'El puntaje')->en('puntaje', ['1', '2', '3', '4', '5'], 'Elige de 1 a 5 estrellas.')
            ->max('comentario', 400);
        $this->validar($v, url('/calificar', ['tipo' => $tipo, 'id' => $id]), $d);
        if ($this->intentar(function () use ($u, $tipo, $id, $d, $s) {
            $k = Calificacion::crear((int) $u['id'], $tipo, $id, (int) $d['puntaje'], $d['comentario']);
            Bitacora::registrar('CALIFICAR', 'REPORTES', 'calificacion', $k, "{$s['nombre']}: {$d['puntaje']} de 5");
        }, $d)) {
            flash('ok', '¡Gracias por calificar nuestro servicio!');
        }
        redirect(self::regreso($tipo));
    }

    /** Lista para el taller, con promedio y filtros. */
    public function index(): void
    {
        Auth::requerir('calificaciones.gestionar');
        $f = ['tipo' => entrada('tipo'), 'puntaje' => entrada('puntaje'), 'respuesta' => entrada('respuesta')];
        $pag = new Paginador(Calificacion::contar($f));
        $this->vista('P5_Reportes/calificaciones', [
            'resumen' => Calificacion::resumen(),
            'lista'   => Calificacion::listar($f, $pag->porPagina, $pag->offset),
            'f'       => $f,
            'pag'     => $pag,
        ]);
    }

    public function responder(): void
    {
        $u = Auth::requerir('calificaciones.gestionar');
        $k = Calificacion::buscar($this->id()) ?? $this->noEncontrado();
        $respuesta = entrada('respuesta');
        if (mb_strlen($respuesta) < 3 || mb_strlen($respuesta) > 400) {
            flash('error', 'Escriba una respuesta de 3 a 400 caracteres.');
        } elseif ($this->intentar(fn() => Calificacion::responder((int) $k['id'], (int) $u['id'], $respuesta))) {
            Bitacora::registrar('RESPONDER', 'REPORTES', 'calificacion', (int) $k['id'], $respuesta);
            flash('ok', 'Respuesta enviada.');
        }
        redirect('/calificaciones');
    }

    private static function regreso(string $tipo): string
    {
        return $tipo === 'PEDIDO' ? '/mis-pedidos' : '/portal';
    }
}
