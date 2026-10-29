<?php
/**
 * SESION_CLASE y RESERVA · Calendario de clases, reservas y asistencia (CU09–CU11).
 * (No confundir con la clase Session del núcleo, que guarda las sesiones de PHP.)
 */

declare(strict_types=1);

final class Sesion
{
    private const SELECT = "select s.*, h.taller_id, t.nombre as taller, t.publico, t.nivel,
                                   u.nombre || ' ' || u.apellido as maestra,
                                   (select count(*) from reserva r where r.sesion_id = s.id and r.estado <> 'CANCELADA') as ocupados
                            from sesion_clase s
                            join horario h on h.id = s.horario_id
                            join taller t on t.id = h.taller_id
                            join maestra m on m.id = s.maestra_id join usuario u on u.id = m.usuario_id";

    /** Clases entre dos fechas (calendario semanal). */
    public static function entre(string $desde, string $hasta, ?int $maestraId = null, ?int $tallerId = null): array
    {
        return Database::filas(
            self::SELECT . ' where s.fecha between cast(:d as date) and cast(:h as date)
               and (cast(:m as integer) is null or s.maestra_id = :m)
               and (cast(:t as integer) is null or h.taller_id = :t)
             order by s.fecha, s.hora_inicio, t.nombre',
            ['d' => $desde, 'h' => $hasta, 'm' => $maestraId, 't' => $tallerId]
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila(self::SELECT . ' where s.id = :id', ['id' => $id]);
    }

    /** Clases futuras con cupo para reservar (portal del alumno y recepción). */
    public static function disponibles(?int $tallerId, ?int $alumnoId, int $dias = 28): array
    {
        return Database::filas(
            self::SELECT . " where s.estado = 'PROGRAMADA'
               and (s.fecha > mitsu.hoy_bo() or (s.fecha = mitsu.hoy_bo() and s.hora_inicio > (now() at time zone 'America/La_Paz')::time))
               and s.fecha <= mitsu.hoy_bo() + :dias
               and (cast(:t as integer) is null or h.taller_id = :t)
               and (cast(:a as integer) is null or not exists (select 1 from reserva r where r.sesion_id = s.id and r.alumno_id = :a and r.estado <> 'CANCELADA'))
             order by s.fecha, s.hora_inicio",
            ['t' => $tallerId, 'a' => $alumnoId, 'dias' => $dias]
        );
    }

    public static function reservas(int $sesionId): array
    {
        return Database::filas(
            "select r.*, a.nivel, a.ci, u.nombre || ' ' || u.apellido as alumno, u.telefono
             from reserva r join alumno a on a.id = r.alumno_id join usuario u on u.id = a.usuario_id
             where r.sesion_id = :s order by r.estado = 'CANCELADA', u.nombre, u.apellido",
            ['s' => $sesionId]
        );
    }

    public static function generar(string $desde, string $hasta): int
    {
        return (int) Database::valor('select mitsu.fn_generar_sesiones(cast(:d as date), cast(:h as date))', ['d' => $desde, 'h' => $hasta]);
    }

    public static function cancelar(int $id, string $motivo): void
    {
        Auth::transaccion(fn() => Database::query(
            "update sesion_clase set estado = 'CANCELADA', motivo_cancelacion = :m where id = :id and estado = 'PROGRAMADA'",
            ['m' => $motivo, 'id' => $id]
        ));
    }

    public static function cambiarMaestra(int $id, int $maestraId): void
    {
        Database::query("update sesion_clase set maestra_id = :m where id = :id and estado = 'PROGRAMADA'", ['m' => $maestraId, 'id' => $id]);
    }

    // ------------------------------------------------------------- Reservas

    public static function reservar(int $sesionId, int $alumnoId): int
    {
        return (int) Database::valor('select mitsu.fn_reservar_clase(:s, :a, :u)', ['s' => $sesionId, 'a' => $alumnoId, 'u' => Auth::id()]);
    }

    public static function reserva(int $id): ?array
    {
        return Database::fila(
            "select r.*, s.fecha, s.hora_inicio, s.estado as estado_sesion, t.nombre as taller
             from reserva r join sesion_clase s on s.id = r.sesion_id
             join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id
             where r.id = :id",
            ['id' => $id]
        );
    }

    public static function cancelarReserva(int $id, string $motivo): void
    {
        Database::query("update reserva set estado = 'CANCELADA', motivo_cancelacion = :m where id = :id",
            ['m' => $motivo, 'id' => $id]);
    }

    /**
     * Registra la asistencia de toda la clase. $marcas: reserva_id => ASISTIO|FALTO.
     * Al terminar, la clase queda como REALIZADA.
     */
    public static function registrarAsistencia(int $sesionId, array $marcas): int
    {
        return Auth::transaccion(function () use ($sesionId, $marcas) {
            $n = 0;
            foreach ($marcas as $reservaId => $estado) {
                if (!in_array($estado, ['ASISTIO', 'FALTO'], true)) {
                    continue;
                }
                $n += Database::query(
                    "update reserva set estado = :e where id = :r and sesion_id = :s and estado <> 'CANCELADA' and estado <> :e",
                    ['e' => $estado, 'r' => (int) $reservaId, 's' => $sesionId]
                )->rowCount();
            }
            Database::query("update sesion_clase set estado = 'REALIZADA' where id = :s and estado = 'PROGRAMADA' and fecha <= mitsu.hoy_bo()",
                ['s' => $sesionId]);
            return $n;
        });
    }

    /** Reservas de un alumno (portal): próximas y su historial. */
    public static function reservasDeAlumno(int $alumnoId, bool $futuras): array
    {
        return Database::filas(
            "select r.*, s.fecha, s.hora_inicio, s.hora_fin, s.estado as estado_sesion, s.motivo_cancelacion as motivo_sesion,
                    t.nombre as taller, u.nombre || ' ' || u.apellido as maestra
             from reserva r join sesion_clase s on s.id = r.sesion_id
             join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id
             join maestra m on m.id = s.maestra_id join usuario u on u.id = m.usuario_id
             where r.alumno_id = :a and " . ($futuras
                ? "s.fecha >= mitsu.hoy_bo() and r.estado = 'RESERVADA' order by s.fecha, s.hora_inicio"
                : "(s.fecha < mitsu.hoy_bo() or r.estado <> 'RESERVADA') order by s.fecha desc, s.hora_inicio desc limit 60"),
            ['a' => $alumnoId]
        );
    }
}
