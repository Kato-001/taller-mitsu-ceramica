<?php
/**
 * TALLER y HORARIO · Cursos que ofrece el estudio y su horario semanal (CU08, CU09).
 */

declare(strict_types=1);

final class Taller
{
    public static function todos(bool $soloActivos = false): array
    {
        return Database::filas(
            "select t.*, (select count(*) from horario h where h.taller_id = t.id and h.estado = 'ACTIVO') as horarios
             from taller t " . ($soloActivos ? "where t.estado = 'ACTIVO' " : '') . 'order by t.estado, t.id'
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila('select * from taller where id = :id', ['id' => $id]);
    }

    public static function guardar(?int $id, array $d): int
    {
        $p = ['n' => $d['nombre'], 'd' => $d['descripcion'], 'ni' => $d['nivel'], 'pu' => $d['publico'],
              'du' => (int) $d['duracion_min'], 'pc' => $d['precio_clase'], 'pm' => $d['precio_mensual']];
        if ($id) {
            Database::query('update taller set nombre = :n, descripcion = :d, nivel = :ni, publico = :pu, duracion_min = :du,
                             precio_clase = cast(:pc as numeric), precio_mensual = cast(:pm as numeric) where id = :id', $p + ['id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into taller (nombre, descripcion, nivel, publico, duracion_min, precio_clase, precio_mensual)
                                      values (:n, :d, :ni, :pu, :du, cast(:pc as numeric), cast(:pm as numeric)) returning id', $p);
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        if ($estado === 'INACTIVO' && Database::valor("select 1 from horario where taller_id = :id and estado = 'ACTIVO'", ['id' => $id])) {
            throw new AppException('El taller tiene horarios activos. Desactívelos primero.');
        }
        Database::query('update taller set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }

    // ------------------------------------------------------------- Horarios

    public static function horarios(): array
    {
        return Database::filas(
            "select h.*, t.nombre as taller, t.publico, u.nombre || ' ' || u.apellido as maestra
             from horario h
             join taller t on t.id = h.taller_id
             join maestra m on m.id = h.maestra_id join usuario u on u.id = m.usuario_id
             order by h.estado, h.dia_semana, h.hora_inicio, t.nombre"
        );
    }

    public static function horario(int $id): ?array
    {
        return Database::fila('select * from horario where id = :id', ['id' => $id]);
    }

    public static function guardarHorario(?int $id, array $d): int
    {
        $p = ['t' => (int) $d['taller_id'], 'm' => (int) $d['maestra_id'], 'd' => (int) $d['dia_semana'],
              'hi' => $d['hora_inicio'], 'hf' => $d['hora_fin'], 'c' => (int) $d['cupo']];
        if ($id) {
            Database::query('update horario set taller_id = :t, maestra_id = :m, dia_semana = :d, hora_inicio = cast(:hi as time),
                             hora_fin = cast(:hf as time), cupo = :c where id = :id', $p + ['id' => $id]);
            return $id;
        }
        return (int) Database::valor('insert into horario (taller_id, maestra_id, dia_semana, hora_inicio, hora_fin, cupo)
                                      values (:t, :m, :d, cast(:hi as time), cast(:hf as time), :c) returning id', $p);
    }

    public static function cambiarEstadoHorario(int $id, string $estado): void
    {
        Database::query('update horario set estado = :e where id = :id', ['e' => $estado, 'id' => $id]);
    }
}
