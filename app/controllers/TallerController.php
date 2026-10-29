<?php
/**
 * CU08 · Gestionar talleres · CU09 · Programar horarios semanales.
 */

declare(strict_types=1);

final class TallerController extends Controlador
{
    public function index(): void
    {
        Auth::requerir('talleres.gestionar');
        $editar = entrada_int('editar');
        $this->vista('P2_Clases/talleres/index', [
            'talleres' => Taller::todos(),
            'editar'   => $editar ? Taller::buscar($editar) : null,
        ]);
    }

    public function guardar(): void
    {
        Auth::requerir('talleres.gestionar');
        $id = entrada_int('id');
        $d = ['nombre' => entrada('nombre'), 'descripcion' => entrada('descripcion'), 'nivel' => entrada('nivel'), 'publico' => entrada('publico'),
              'duracion_min' => entrada('duracion_min'), 'precio_clase' => str_replace(',', '.', entrada('precio_clase')),
              'precio_mensual' => str_replace(',', '.', entrada('precio_mensual'))];
        $v = (new Validator($d))
            ->requerido('nombre', 'El nombre')->longitud('nombre', 3, 80)
            ->requerido('descripcion', 'La descripción')->longitud('descripcion', 10, 500)
            ->requerido('nivel', 'El nivel')->en('nivel', array_keys(NIVELES))
            ->requerido('publico', 'El público')->en('publico', array_keys(PUBLICOS))
            ->requerido('duracion_min', 'La duración')->entero('duracion_min', 30, 300)
            ->requerido('precio_clase', 'El precio por clase')->decimal('precio_clase', 1, 5000)
            ->requerido('precio_mensual', 'El precio mensual')->decimal('precio_mensual', 1, 20000);
        $this->validar($v, url('/talleres', ['editar' => $id]), $d);
        if ($this->intentar(function () use ($id, $d) {
            $n = Taller::guardar($id, $d);
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'CLASES', 'taller', $n, "Taller {$d['nombre']}");
        }, $d)) {
            flash('ok', $id ? 'Taller actualizado.' : 'Taller registrado. Ahora programe sus horarios.');
            redirect('/talleres');
        }
        redirect(url('/talleres', ['editar' => $id]));
    }

    public function estado(): void
    {
        Auth::requerir('talleres.gestionar');
        $t = Taller::buscar($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($t['estado']);
        if ($this->intentar(fn() => Taller::cambiarEstado((int) $t['id'], $nuevo))) {
            Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'CLASES', 'taller', (int) $t['id'], "Taller {$t['nombre']}");
            flash('ok', 'Taller ' . ($nuevo === 'ACTIVO' ? 'activado.' : 'desactivado.'));
        }
        redirect('/talleres');
    }

    // ------------------------------------------------------------- Horarios

    public function horarios(): void
    {
        Auth::requerir('talleres.gestionar');
        $editar = entrada_int('editar');
        $this->vista('P2_Clases/horarios/index', [
            'horarios' => Taller::horarios(),
            'talleres' => Taller::todos(true),
            'maestras' => Maestra::activas(),
            'editar'   => $editar ? Taller::horario($editar) : null,
        ]);
    }

    public function guardarHorario(): void
    {
        Auth::requerir('talleres.gestionar');
        $id = entrada_int('id');
        $d = ['taller_id' => entrada('taller_id'), 'maestra_id' => entrada('maestra_id'), 'dia_semana' => entrada('dia_semana'),
              'hora_inicio' => entrada('hora_inicio'), 'hora_fin' => entrada('hora_fin'), 'cupo' => entrada('cupo')];
        $v = (new Validator($d))
            ->requerido('taller_id', 'El taller')->entero('taller_id', 1)
            ->requerido('maestra_id', 'La maestra')->entero('maestra_id', 1)
            ->requerido('dia_semana', 'El día')->entero('dia_semana', 1, 7)
            ->requerido('hora_inicio', 'La hora de inicio')->hora('hora_inicio')
            ->requerido('hora_fin', 'La hora de fin')->hora('hora_fin')
            ->si($d['hora_inicio'] !== '' && $d['hora_fin'] !== '' && $d['hora_fin'] <= $d['hora_inicio'], 'hora_fin', 'La hora de fin debe ser posterior al inicio.')
            ->si($d['hora_inicio'] !== '' && ($d['hora_inicio'] < '07:00' || $d['hora_fin'] > '22:00'), 'hora_inicio', 'El taller atiende de 07:00 a 22:00.')
            ->requerido('cupo', 'El cupo')->entero('cupo', 1, 30);
        $this->validar($v, url('/horarios', ['editar' => $id]), $d);
        if ($this->intentar(function () use ($id, $d) {
            $n = Taller::guardarHorario($id, $d);
            Bitacora::registrar($id ? 'EDITAR' : 'CREAR', 'CLASES', 'horario', $n,
                DIAS_SEMANA[(int) $d['dia_semana']] . " {$d['hora_inicio']}-{$d['hora_fin']}");
        }, $d)) {
            flash('ok', ($id ? 'Horario actualizado.' : 'Horario programado.') . ' Genere las clases del calendario para que aparezcan para reservar.');
            redirect('/horarios');
        }
        redirect(url('/horarios', ['editar' => $id]));
    }

    public function estadoHorario(): void
    {
        Auth::requerir('talleres.gestionar');
        $h = Taller::horario($this->id()) ?? $this->noEncontrado();
        $nuevo = $this->estadoContrario($h['estado']);
        if ($this->intentar(fn() => Taller::cambiarEstadoHorario((int) $h['id'], $nuevo))) {
            Bitacora::registrar($nuevo === 'ACTIVO' ? 'ACTIVAR' : 'DESACTIVAR', 'CLASES', 'horario', (int) $h['id'], 'Horario ' . DIAS_SEMANA[(int) $h['dia_semana']]);
            flash('ok', $nuevo === 'ACTIVO' ? 'Horario activado.' : 'Horario desactivado: ya no se generan clases nuevas con él.');
        }
        redirect('/horarios');
    }
}
