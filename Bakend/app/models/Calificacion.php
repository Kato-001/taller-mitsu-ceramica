<?php
/**
 * CALIFICACION · Puntaje de 1 a 5 de un pedido entregado o de una clase asistida (CU26).
 */

declare(strict_types=1);

final class Calificacion
{
    public const TIPOS = ['PEDIDO' => 'Compra en la tienda', 'CLASE' => 'Clase'];

    /** Servicio que el usuario puede calificar; null si no es suyo o aún no lo recibió. */
    public static function servicio(string $tipo, int $id, int $usuario): ?array
    {
        $sql = $tipo === 'PEDIDO'
            ? "select p.id, 'Pedido ' || p.numero as nombre, coalesce(p.entregado_en, p.fecha)::date as fecha, k.id as calificacion_id
                 from pedido p left join calificacion k on k.pedido_id = p.id
                where p.id = :i and p.usuario_id = :u and p.estado = 'ENTREGADO'"
            : "select r.id, 'Clase de ' || t.nombre as nombre, s.fecha, k.id as calificacion_id
                 from reserva r join alumno a on a.id = r.alumno_id join sesion_clase s on s.id = r.sesion_id
                 join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id
                 left join calificacion k on k.reserva_id = r.id
                where r.id = :i and a.usuario_id = :u and r.estado = 'ASISTIO'";
        return Database::fila($sql, ['i' => $id, 'u' => $usuario]);
    }

    /** Puntajes que ya dio el usuario: [id del pedido o de la reserva => puntaje]. */
    public static function puntajes(string $tipo, int $usuario): array
    {
        $col = $tipo === 'PEDIDO' ? 'pedido_id' : 'reserva_id';
        return array_column(Database::filas("select $col as id, puntaje from calificacion where tipo = :t and usuario_id = :u", ['t' => $tipo, 'u' => $usuario]), 'puntaje', 'id');
    }

    public static function crear(int $usuario, string $tipo, int $servicio, int $puntaje, string $comentario): int
    {
        return (int) Database::valor(
            'insert into calificacion (usuario_id, tipo, pedido_id, reserva_id, puntaje, comentario) values (:u, :t, :p, :r, :n, :c) returning id',
            ['u' => $usuario, 't' => $tipo, 'p' => $tipo === 'PEDIDO' ? $servicio : null, 'r' => $tipo === 'CLASE' ? $servicio : null,
             'n' => $puntaje, 'c' => $comentario !== '' ? $comentario : null]
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::fila('select * from calificacion where id = :i', ['i' => $id]);
    }

    public static function responder(int $id, int $usuario, string $respuesta): void
    {
        Database::query('update calificacion set respuesta = :r, respondido_por = :u, respondido_en = now() where id = :i',
            ['r' => $respuesta, 'u' => $usuario, 'i' => $id]);
    }

    public static function resumen(): array
    {
        return Database::fila(
            "select count(*) as total, coalesce(round(avg(puntaje), 1), 0) as promedio,
                    coalesce(round(avg(puntaje) filter (where tipo = 'PEDIDO'), 1), 0) as pedidos,
                    coalesce(round(avg(puntaje) filter (where tipo = 'CLASE'), 1), 0) as clases,
                    count(*) filter (where respuesta is null) as sin_respuesta
               from calificacion"
        );
    }

    public static function listar(array $f, int $limite, int $offset): array
    {
        [$w, $p] = self::filtros($f);
        return Database::filas(
            "select k.*, u.nombre || ' ' || u.apellido as persona, pd.numero, t.nombre as taller, s.fecha as fecha_clase
               from calificacion k join usuario u on u.id = k.usuario_id left join pedido pd on pd.id = k.pedido_id
               left join reserva r on r.id = k.reserva_id left join sesion_clase s on s.id = r.sesion_id
               left join horario h on h.id = s.horario_id left join taller t on t.id = h.taller_id
               $w order by k.creado_en desc limit $limite offset $offset",
            $p
        );
    }

    public static function contar(array $f): int
    {
        [$w, $p] = self::filtros($f);
        return (int) Database::valor("select count(*) from calificacion k $w", $p);
    }

    public static function estrellas(int $n): string
    {
        return '<span class="estrellas" role="img" aria-label="' . $n . ' de 5 estrellas">' . str_repeat('★', $n) . '<i>' . str_repeat('★', 5 - $n) . '</i></span>';
    }

    private static function filtros(array $f): array
    {
        $w = [];
        $p = [];
        if (isset(self::TIPOS[$f['tipo'] ?? ''])) {
            $w[] = 'k.tipo = :t';
            $p['t'] = $f['tipo'];
        }
        if (in_array($f['puntaje'] ?? '', ['1', '2', '3', '4', '5'], true)) {
            $w[] = 'k.puntaje = :n';
            $p['n'] = (int) $f['puntaje'];
        }
        if (($f['respuesta'] ?? '') === 'NO') {
            $w[] = 'k.respuesta is null';
        }
        return [$w ? 'where ' . implode(' and ', $w) : '', $p];
    }
}
