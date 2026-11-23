<?php
/**
 * Catálogo de reportes del sistema (CU23, CU24): de qué tablas sale cada uno y
 * qué campos se pueden mostrar, filtrar, ordenar y agrupar.
 *
 * campo => [etiqueta, expresión SQL, tipo (texto|opcion|numero|dinero|fecha), ¿se suma en el total?]
 */

declare(strict_types=1);

final class CatalogoReportes
{
    private static function dia(string $columna): string
    {
        return "($columna at time zone 'America/La_Paz')::date";
    }

    private static function persona(string $alias): string
    {
        return "$alias.nombre || ' ' || $alias.apellido";
    }

    public static function tipos(): array
    {
        return [
            'piezas' => ['titulo' => 'Productos (piezas)', 'from' => 'pieza p join categoria c on c.id = p.categoria_id', 'orden' => 'nombre', 'dir' => 'asc',
                'campos' => [
                    'codigo'    => ['Código', 'p.codigo', 'texto'],
                    'nombre'    => ['Producto', 'p.nombre', 'texto'],
                    'categoria' => ['Categoría', 'c.nombre', 'opcion'],
                    'precio'    => ['Precio', 'p.precio', 'dinero'],
                    'stock'     => ['Stock', 'p.stock', 'numero', true],
                    'minimo'    => ['Stock mínimo', 'p.stock_minimo', 'numero'],
                    'valor'     => ['Valor en stock', 'p.stock * p.precio', 'dinero', true],
                    'vendidas'  => ['Vendidas', "(select coalesce(sum(d.cantidad), 0) from detalle_venta d join venta v on v.id = d.venta_id where d.pieza_id = p.id and v.estado = 'VALIDA')
                                    + (select coalesce(sum(d.cantidad), 0) from detalle_pedido d join pedido q on q.id = d.pedido_id where d.pieza_id = p.id and q.estado in ('PAGADO', 'ENTREGADO'))", 'numero', true],
                    'estado'    => ['Estado', 'p.estado', 'opcion'],
                    'registro'  => ['Registrado', self::dia('p.creado_en'), 'fecha'],
                ]],
            'ventas' => ['titulo' => 'Ventas en caja', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'venta v join cliente c on c.id = v.cliente_id join usuario u on u.id = v.usuario_id join metodo_pago m on m.id = v.metodo_pago_id',
                'campos' => [
                    'numero'   => ['N.º', 'v.numero', 'texto'],
                    'fecha'    => ['Fecha', self::dia('v.fecha'), 'fecha'],
                    'cliente'  => ['Cliente', 'c.nombre', 'texto'],
                    'ci_nit'   => ['CI/NIT', 'c.ci_nit', 'texto'],
                    'vendedor' => ['Vendedor', self::persona('u'), 'opcion'],
                    'metodo'   => ['Método de pago', 'm.nombre', 'opcion'],
                    'caja'     => ['Caja N.º', 'v.caja_id', 'numero'],
                    'unidades' => ['Unidades', '(select coalesce(sum(d.cantidad), 0) from detalle_venta d where d.venta_id = v.id)', 'numero', true],
                    'total'    => ['Total', 'v.total', 'dinero', true],
                    'estado'   => ['Estado', 'v.estado', 'opcion'],
                ]],
            'vendidos' => ['titulo' => 'Productos vendidos (detalle)', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'detalle_venta d join venta v on v.id = d.venta_id join pieza p on p.id = d.pieza_id join categoria g on g.id = p.categoria_id join cliente c on c.id = v.cliente_id',
                'campos' => [
                    'venta'     => ['Venta', 'v.numero', 'texto'],
                    'fecha'     => ['Fecha', self::dia('v.fecha'), 'fecha'],
                    'codigo'    => ['Código', 'p.codigo', 'texto'],
                    'producto'  => ['Producto', 'p.nombre', 'opcion'],
                    'categoria' => ['Categoría', 'g.nombre', 'opcion'],
                    'cliente'   => ['Cliente', 'c.nombre', 'texto'],
                    'cantidad'  => ['Cantidad', 'd.cantidad', 'numero', true],
                    'precio'    => ['Precio unit.', 'd.precio_unitario', 'dinero'],
                    'subtotal'  => ['Subtotal', 'd.subtotal', 'dinero', true],
                    'estado'    => ['Estado', 'v.estado', 'opcion'],
                ]],
            'pedidos' => ['titulo' => 'Pedidos de la tienda', 'from' => 'pedido p join cliente c on c.id = p.cliente_id', 'orden' => 'fecha', 'dir' => 'desc',
                'campos' => [
                    'numero'   => ['N.º', 'p.numero', 'texto'],
                    'fecha'    => ['Fecha', self::dia('p.fecha'), 'fecha'],
                    'cliente'  => ['Cliente', 'c.nombre', 'texto'],
                    'telefono' => ['Teléfono', 'p.telefono', 'texto'],
                    'entrega'  => ['Entrega', 'p.entrega', 'opcion'],
                    'metodo'   => ['Pago', "(select g.metodo from pago g where g.pedido_id = p.id order by (g.estado = 'APROBADO') desc, g.id desc limit 1)", 'opcion'],
                    'unidades' => ['Unidades', '(select coalesce(sum(d.cantidad), 0) from detalle_pedido d where d.pedido_id = p.id)', 'numero', true],
                    'total'    => ['Total', 'p.total', 'dinero', true],
                    'estado'   => ['Estado', 'p.estado', 'opcion'],
                    'pagado'   => ['Pagado el', self::dia('p.pagado_en'), 'fecha'],
                ]],
            'compras' => ['titulo' => 'Compras a proveedores', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'compra k join proveedor p on p.id = k.proveedor_id join usuario u on u.id = k.usuario_id',
                'campos' => [
                    'numero'    => ['N.º', 'k.numero', 'texto'],
                    'fecha'     => ['Fecha', 'k.fecha', 'fecha'],
                    'proveedor' => ['Proveedor', 'p.nombre', 'opcion'],
                    'ciudad'    => ['Ciudad', 'p.ciudad', 'opcion'],
                    'tipo'      => ['Tipo', 'k.tipo', 'opcion'],
                    'usuario'   => ['Registró', self::persona('u'), 'opcion'],
                    'items'     => ['Ítems', '(select count(*) from detalle_compra d where d.compra_id = k.id)', 'numero', true],
                    'total'     => ['Total', 'k.total', 'dinero', true],
                    'estado'    => ['Estado', 'k.estado', 'opcion'],
                ]],
            'materiales' => ['titulo' => 'Inventario de materiales', 'orden' => 'nombre', 'dir' => 'asc',
                'from' => 'material m join categoria c on c.id = m.categoria_id join unidad n on n.id = m.unidad_id',
                'campos' => [
                    'nombre'    => ['Material', 'm.nombre', 'texto'],
                    'categoria' => ['Categoría', 'c.nombre', 'opcion'],
                    'unidad'    => ['Unidad', 'n.nombre', 'opcion'],
                    'stock'     => ['Stock', 'm.stock', 'numero'],
                    'minimo'    => ['Stock mínimo', 'm.stock_minimo', 'numero'],
                    'alerta'    => ['Alerta', "case when m.stock <= 0 then 'AGOTADO' when m.stock <= m.stock_minimo then 'STOCK_BAJO' else 'SUFICIENTE' end", 'opcion'],
                    'costo'     => ['Costo promedio', 'm.costo_promedio', 'dinero'],
                    'valor'     => ['Valor en stock', 'round(m.stock * m.costo_promedio, 2)', 'dinero', true],
                    'estado'    => ['Estado', 'm.estado', 'opcion'],
                ]],
            'kardex' => ['titulo' => 'Movimientos de materiales (kardex)', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'movimiento_material k join material m on m.id = k.material_id join categoria c on c.id = m.categoria_id join usuario u on u.id = k.usuario_id',
                'campos' => [
                    'fecha'     => ['Fecha', self::dia('k.fecha'), 'fecha'],
                    'material'  => ['Material', 'm.nombre', 'opcion'],
                    'categoria' => ['Categoría', 'c.nombre', 'opcion'],
                    'tipo'      => ['Movimiento', 'k.tipo', 'opcion'],
                    'cantidad'  => ['Cantidad', 'k.cantidad', 'numero'],
                    'saldo'     => ['Saldo', 'k.stock_resultante', 'numero'],
                    'motivo'    => ['Motivo', 'k.motivo', 'texto'],
                    'usuario'   => ['Usuario', self::persona('u'), 'opcion'],
                ]],
            'reservas' => ['titulo' => 'Reservas y asistencia', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'reserva r join sesion_clase s on s.id = r.sesion_id join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id
                           join alumno a on a.id = r.alumno_id join usuario u on u.id = a.usuario_id join maestra m on m.id = s.maestra_id join usuario w on w.id = m.usuario_id',
                'campos' => [
                    'fecha'     => ['Fecha de clase', 's.fecha', 'fecha'],
                    'hora'      => ['Hora', "to_char(s.hora_inicio, 'HH24:MI')", 'texto'],
                    'taller'    => ['Taller', 't.nombre', 'opcion'],
                    'maestra'   => ['Maestra', self::persona('w'), 'opcion'],
                    'alumno'    => ['Alumno', self::persona('u'), 'texto'],
                    'nivel'     => ['Nivel', 'a.nivel', 'opcion'],
                    'precio'    => ['Precio clase', 't.precio_clase', 'dinero'],
                    'estado'    => ['Estado', 'r.estado', 'opcion'],
                    'reservado' => ['Reservado el', self::dia('r.creado_en'), 'fecha'],
                ]],
            'clases' => ['titulo' => 'Clases y ocupación', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'sesion_clase s join horario h on h.id = s.horario_id join taller t on t.id = h.taller_id join maestra m on m.id = s.maestra_id join usuario w on w.id = m.usuario_id',
                'campos' => [
                    'fecha'      => ['Fecha', 's.fecha', 'fecha'],
                    'taller'     => ['Taller', 't.nombre', 'opcion'],
                    'publico'    => ['Público', 't.publico', 'opcion'],
                    'maestra'    => ['Maestra', self::persona('w'), 'opcion'],
                    'cupo'       => ['Cupo', 's.cupo', 'numero', true],
                    'ocupados'   => ['Reservas', "(select count(*) from reserva r where r.sesion_id = s.id and r.estado <> 'CANCELADA')", 'numero', true],
                    'asistieron' => ['Asistieron', "(select count(*) from reserva r where r.sesion_id = s.id and r.estado = 'ASISTIO')", 'numero', true],
                    'estado'     => ['Estado', 's.estado', 'opcion'],
                ]],
            'alumnos' => ['titulo' => 'Alumnos', 'from' => 'alumno a join usuario u on u.id = a.usuario_id', 'orden' => 'alumno', 'dir' => 'asc',
                'campos' => [
                    'alumno'      => ['Alumno', self::persona('u'), 'texto'],
                    'ci'          => ['CI', 'a.ci', 'texto'],
                    'correo'      => ['Correo', 'u.correo', 'texto'],
                    'telefono'    => ['Teléfono', 'u.telefono', 'texto'],
                    'nivel'       => ['Nivel', 'a.nivel', 'opcion'],
                    'edad'        => ['Edad', 'extract(year from age(a.fecha_nacimiento))::int', 'numero'],
                    'asistencias' => ['Asistencias', "(select count(*) from reserva r where r.alumno_id = a.id and r.estado = 'ASISTIO')", 'numero', true],
                    'faltas'      => ['Faltas', "(select count(*) from reserva r where r.alumno_id = a.id and r.estado = 'FALTO')", 'numero', true],
                    'estado'      => ['Estado', 'a.estado', 'opcion'],
                    'registro'    => ['Registrado', self::dia('u.creado_en'), 'fecha'],
                ]],
            'clientes' => ['titulo' => 'Clientes', 'from' => 'cliente c', 'orden' => 'nombre', 'dir' => 'asc',
                'campos' => [
                    'nombre'   => ['Cliente', 'c.nombre', 'texto'],
                    'ci_nit'   => ['CI/NIT', 'c.ci_nit', 'texto'],
                    'telefono' => ['Teléfono', 'c.telefono', 'texto'],
                    'correo'   => ['Correo', 'c.correo', 'texto'],
                    'compras'  => ['Compras en caja', "(select count(*) from venta v where v.cliente_id = c.id and v.estado = 'VALIDA')", 'numero', true],
                    'gastado'  => ['Total en caja', "(select coalesce(sum(v.total), 0) from venta v where v.cliente_id = c.id and v.estado = 'VALIDA')", 'dinero', true],
                    'web'      => ['Total en la tienda', "(select coalesce(sum(p.total), 0) from pedido p where p.cliente_id = c.id and p.estado in ('PAGADO', 'ENTREGADO'))", 'dinero', true],
                    'estado'   => ['Estado', 'c.estado', 'opcion'],
                    'registro' => ['Registrado', self::dia('c.creado_en'), 'fecha'],
                ]],
            'cajas' => ['titulo' => 'Cajas y arqueos', 'from' => 'caja k join usuario u on u.id = k.usuario_id', 'orden' => 'numero', 'dir' => 'desc',
                'campos' => [
                    'numero'     => ['Caja N.º', 'k.id', 'numero'],
                    'abierta'    => ['Abierta', self::dia('k.abierta_en'), 'fecha'],
                    'cerrada'    => ['Cerrada', self::dia('k.cerrada_en'), 'fecha'],
                    'usuario'    => ['Responsable', self::persona('u'), 'opcion'],
                    'inicial'    => ['Monto inicial', 'k.monto_inicial', 'dinero'],
                    'efectivo'   => ['Efectivo', 'k.total_efectivo', 'dinero', true],
                    'otros'      => ['Otros medios', 'k.total_otros', 'dinero', true],
                    'declarado'  => ['Declarado', 'k.monto_declarado', 'dinero'],
                    'diferencia' => ['Diferencia', 'k.diferencia', 'dinero', true],
                    'estado'     => ['Estado', 'k.estado', 'opcion'],
                ]],
            'calificaciones' => ['titulo' => 'Calificaciones del servicio', 'permiso' => 'calificaciones.gestionar', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'calificacion k join usuario u on u.id = k.usuario_id',
                'campos' => [
                    'fecha'      => ['Fecha', self::dia('k.creado_en'), 'fecha'],
                    'persona'    => ['Persona', self::persona('u'), 'texto'],
                    'tipo'       => ['Servicio', 'k.tipo', 'opcion'],
                    'puntaje'    => ['Puntaje', 'k.puntaje', 'numero'],
                    'comentario' => ['Comentario', 'k.comentario', 'texto'],
                    'respuesta'  => ['Respuesta', 'k.respuesta', 'texto'],
                ]],
            'bitacora' => ['titulo' => 'Bitácora', 'permiso' => 'bitacora.ver', 'orden' => 'fecha', 'dir' => 'desc',
                'from' => 'bitacora b left join usuario u on u.id = b.usuario_id',
                'campos' => [
                    'fecha'   => ['Fecha', self::dia('b.fecha'), 'fecha'],
                    'usuario' => ['Usuario', "coalesce(u.nombre || ' ' || u.apellido, 'Sistema')", 'opcion'],
                    'accion'  => ['Acción', 'b.accion', 'opcion'],
                    'modulo'  => ['Módulo', 'b.modulo', 'opcion'],
                    'detalle' => ['Detalle', 'b.detalle', 'texto'],
                    'ip'      => ['IP', 'b.ip', 'texto'],
                ]],
        ];
    }
}
