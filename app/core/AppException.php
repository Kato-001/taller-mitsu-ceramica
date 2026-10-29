<?php
/**
 * Error de negocio con un mensaje apto para mostrar al usuario.
 */

declare(strict_types=1);

final class AppException extends RuntimeException
{
    /** Convierte un error de PostgreSQL en un mensaje claro, sin detalles internos. */
    public static function desdePdo(PDOException $e): self
    {
        $estado = (string) ($e->errorInfo[0] ?? $e->getCode());
        $texto = (string) ($e->errorInfo[2] ?? $e->getMessage());

        // RAISE EXCEPTION de triggers y procedimientos: el mensaje ya está redactado para el usuario
        if ($estado === 'P0001') {
            return new self((string) preg_replace('/^ERROR:\s*/', '', strtok($texto, "\n")));
        }

        $mensaje = match ($estado) {
            '23P01' => str_contains($texto, 'horario_sin_cruce_maestra')
                ? 'La maestra ya tiene otro horario que se cruza ese día.'
                : 'El registro se cruza con otro existente.',
            '23505' => self::duplicado($texto),
            '23503' => 'No se puede completar: el registro está relacionado con otros datos.',
            '23514' => 'Algún dato no cumple las reglas permitidas. Revise el formulario.',
            '22P02', '22007', '22008', '22003' => 'Algún dato tiene un formato inválido.',
            default => null,
        };

        if ($mensaje === null) {
            error_log('[Mitsu] PDO ' . $estado . ': ' . $texto);
            $mensaje = 'No se pudo completar la operación. Intente nuevamente.';
        }
        return new self($mensaje);
    }

    private static function duplicado(string $texto): string
    {
        return match (true) {
            str_contains($texto, 'usuario_correo')    => 'Ese correo ya está registrado.',
            str_contains($texto, 'alumno_ci')         => 'Ya existe un alumno con ese CI.',
            str_contains($texto, 'rol_nombre')        => 'Ya existe un rol con ese nombre.',
            str_contains($texto, 'taller_nombre')     => 'Ya existe un taller con ese nombre.',
            str_contains($texto, 'categoria_nombre')  => 'Ya existe una categoría con ese nombre.',
            str_contains($texto, 'unidad_nombre')     => 'Ya existe una unidad con ese nombre.',
            str_contains($texto, 'metodo_pago')       => 'Ya existe un método de pago con ese nombre.',
            str_contains($texto, 'proveedor_nombre')  => 'Ya existe un proveedor con ese nombre.',
            str_contains($texto, 'proveedor_nit')     => 'Ya existe un proveedor con ese NIT.',
            str_contains($texto, 'material_nombre')   => 'Ya existe un material con ese nombre.',
            str_contains($texto, 'pieza_nombre')      => 'Ya existe una pieza con ese nombre.',
            str_contains($texto, 'pieza_codigo')      => 'Ya existe una pieza con ese código.',
            str_contains($texto, 'cliente_ci_nit')    => 'Ya existe un cliente con ese CI/NIT.',
            str_contains($texto, 'reserva_activa')    => 'El alumno ya tiene una reserva en esa clase.',
            str_contains($texto, 'caja_unica_abierta') => 'Ya hay una caja abierta.',
            str_contains($texto, 'detalle_compra')    => 'Un material está repetido en la compra.',
            default                                   => 'Ya existe un registro con esos datos.',
        };
    }
}
