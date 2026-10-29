# Sugerencias de lo que se puede pedir en cada ciclo

Ideas de cambios o preguntas que la docente podría pedir en la defensa, con dónde se harían en el sistema Mitsü. Sirve para prepararse y para planificar mejoras.

## Ciclo I · Seguridad y configuración

| Posible pedido | Dónde se haría | Dificultad |
|---|---|---|
| Que la contraseña venza cada 90 días | Columna `clave_cambiada_en` en `usuario` y control al ingresar (`Auth`) | Media |
| Ver los usuarios conectados ahora | Consulta sobre la tabla `sesion` (ya guarda la última actividad) | Baja |
| Exportar la bitácora a Excel o PDF | Botón en `SistemaController::bitacora` que genere CSV | Baja |
| Rol nuevo “Cajero” con solo ventas y caja | Pantalla Roles y permisos: crear el rol y marcar `ventas.registrar` y `caja.gestionar` (sin programar) | Muy baja |
| Doble factor por correo al ingresar | Reusar el código de 6 dígitos de `Recuperacion` | Media |
| Bloquear por usuario además de por IP | Contar `LOGIN_FALLIDO` por correo en la bitácora | Baja |

## Ciclo II · Clases y reservas

| Posible pedido | Dónde se haría | Dificultad |
|---|---|---|
| Lista de espera cuando la clase está llena | Estado `ESPERA` en `reserva` y trigger que la sube al cancelarse otra | Media |
| Porcentaje de asistencia por alumno en su portal | Ya existe la consulta Q13; mostrarla en `portal/inicio` | Baja |
| Recuperar una clase faltada | Reserva sin costo marcada como `RECUPERACION` | Media |
| Límite de reservas por semana para cada alumno | Validación en `tg_reserva_validar` | Baja |
| Calendario mensual además del semanal | Vista nueva en `ClaseController::calendario` | Media |
| Aviso a los alumnos cuando se cancela una clase | `Mailer` al ejecutar `Sesion::cancelar` (cuando haya SMTP) | Baja |

## Ciclo III · Inventario, ventas y tienda

| Posible pedido | Dónde se haría | Dificultad |
|---|---|---|
| Descuento por porcentaje en la venta | Columna `descuento` en `venta` y cálculo en `fn_registrar_venta` | Media |
| Reporte de ventas por día y por método de pago | Consultas Q15 y Q26 en una pantalla nueva | Baja |
| Receta de materiales por pieza (cuánta arcilla usa) | Tabla `pieza_material` y consumo automático al registrar producción | Alta |
| Cupones en la tienda en línea | Tabla `cupon` y validación en `fn_crear_pedido` | Media |
| Costo de envío por zona | Campo en configuración y suma en el checkout | Baja |
| Pago real con QR del banco | Contrato con el banco y webhook de confirmación (reemplaza la verificación manual) | Alta |
| Factura en lugar de nota de venta | Integración con Impuestos Nacionales (SIAT) | Alta |

## Ciclo IV · Cafetería, cobros y reportes (planificado)

| Caso de uso | Contenido sugerido |
|---|---|
| CU23 Gestionar menú | Productos de cafetería con categoría, precio, disponibilidad e ingredientes de cultivo propio |
| CU24 Registrar comandas | Pedido por mesa con estados (pendiente, preparando, entregado) y cobro en la misma caja |
| CU25 Mensualidades y morosidad | Plan mensual por taller, cuotas generadas cada mes, pagos y lista de alumnos en mora |
| CU26 Pedidos personalizados | Encargo de piezas a pedido con anticipo, fecha de entrega y estado de producción |
| CU27 Notificaciones | Avisos por correo: reserva confirmada, clase cancelada, stock bajo, cuota vencida |
| CU28 Reportes | Ventas por período, asistencia, inventario valorizado, talleres más solicitados; exportar a PDF y Excel |

## Preguntas de modelado que suelen hacer

- Explicar la diferencia entre «include» y «extend» con un ejemplo del sistema (CU20 incluye CU21; CU16 extiende CU13).
- Mostrar en un diagrama de secuencia dónde está un fragmento `alt` y qué condición tiene.
- Señalar en el diagrama relacional una relación muchos a muchos y su tabla intermedia (`rolpermiso`).
- Mostrar un ejemplo de cada forma normal en las tablas reales.
- Explicar qué hace un trigger y mostrar uno funcionando (por ejemplo, intentar vender más stock del que hay).
