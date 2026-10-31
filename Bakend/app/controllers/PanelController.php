<?php
/**
 * Panel de inicio: indicadores del día según el rol.
 */

declare(strict_types=1);

final class PanelController extends Controlador
{
    public function index(): void
    {
        $u = Auth::requerir('panel.ver');
        if (Auth::puede('clases.propias') && !Auth::puede('sesiones.gestionar', 'asistencia.registrar')) {
            $this->vista('P1_Seguridad/panel_maestra', [
                'hoy'     => Sesion::entre(hoy(), hoy(), (int) $u['maestra_id']),
                'semana'  => Sesion::entre(date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+7 days')), (int) $u['maestra_id']),
                'pendientes' => Database::filas(
                    "select s.id, s.fecha, s.hora_inicio, t.nombre as taller,
                            (select count(*) from reserva r where r.sesion_id = s.id and r.estado = 'RESERVADA') as sin_marcar
                     from sesion_clase s join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id
                     where s.maestra_id = :m and s.fecha < mitsu.hoy_bo() and s.estado <> 'CANCELADA'
                       and exists (select 1 from reserva r where r.sesion_id = s.id and r.estado = 'RESERVADA')
                     order by s.fecha desc limit 5",
                    ['m' => $u['maestra_id']]
                ),
            ]);
            return;
        }

        $k = Database::fila(
            "select
                (select count(*) from sesion_clase where fecha = mitsu.hoy_bo() and estado <> 'CANCELADA') as clases_hoy,
                (select count(*) from reserva r join sesion_clase s on s.id = r.sesion_id
                  where s.fecha = mitsu.hoy_bo() and r.estado <> 'CANCELADA') as alumnos_hoy,
                (select count(*) from alumno where estado = 'ACTIVO') as alumnos,
                (select coalesce(sum(total), 0) from venta where estado = 'VALIDA'
                  and (fecha at time zone 'America/La_Paz')::date = mitsu.hoy_bo()) as ventas_hoy,
                (select coalesce(sum(total), 0) from venta where estado = 'VALIDA'
                  and date_trunc('month', fecha at time zone 'America/La_Paz') = date_trunc('month', mitsu.hoy_bo())) as ventas_mes,
                (select count(*) from material where estado = 'ACTIVO' and stock <= stock_minimo) as materiales_bajos,
                (select count(*) from pieza where estado = 'ACTIVO' and stock <= stock_minimo) as piezas_bajas,
                (select round(100.0 * count(*) filter (where r.estado = 'ASISTIO') / nullif(count(*) filter (where r.estado in ('ASISTIO', 'FALTO')), 0))
                   from reserva r join sesion_clase s on s.id = r.sesion_id where s.fecha >= mitsu.hoy_bo() - 30) as asistencia_30"
        );

        // Ventas de los últimos 7 días (gráfico de barras)
        $dias = Database::filas(
            "select d::date as dia, coalesce(sum(v.total), 0) as total
             from generate_series(mitsu.hoy_bo() - 6, mitsu.hoy_bo(), interval '1 day') d
             left join venta v on (v.fecha at time zone 'America/La_Paz')::date = d::date and v.estado = 'VALIDA'
             group by d order by d"
        );

        $this->vista('P1_Seguridad/panel', [
            'k'          => $k,
            'dias'       => $dias,
            'clasesHoy'  => Sesion::entre(hoy(), hoy()),
            'alertasMat' => array_slice(Material::alertas(), 0, 5),
            'alertasPz'  => array_slice(Pieza::alertas(), 0, 5),
            'caja'       => Venta::cajaAbierta(),
            'actividad'  => Auth::puede('bitacora.ver') ? Bitacora::recientes(6) : [],
            'top'        => Database::filas(
                "select p.nombre, sum(d.cantidad) as unidades, sum(d.subtotal) as total
                 from detalle_venta d join venta v on v.id = d.venta_id join pieza p on p.id = d.pieza_id
                 where v.estado = 'VALIDA' and v.fecha >= now() - interval '30 days'
                 group by p.nombre order by unidades desc limit 5"
            ),
        ]);
    }
}
