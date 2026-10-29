<?php
/**
 * Pasarela de pago del Taller Mitsü (CU21) · MODO DE DEMOSTRACIÓN
 * ---------------------------------------------------------------------
 * Simula la respuesta de un procesador de pagos para los tres métodos:
 *   - QR bancario: el cliente paga con la app de su banco y avisa; el taller
 *     confirma el depósito (CU22).
 *   - Tarjeta: se valida el número (algoritmo de Luhn), la fecha y el CVV.
 *     Tarjetas de prueba: terminada en 0002 → rechazada por fondos; 0069 → vencida;
 *     cualquier otra válida → aprobada.
 *   - PayPal: pantalla de aprobación de prueba (sandbox simulado).
 * No se cobra dinero real. Nunca se guarda el número completo ni el CVV:
 * solo la marca y los 4 últimos dígitos. Para cobrar de verdad se reemplaza
 * este servicio por la API del banco o de PayPal sin tocar los controladores.
 */

declare(strict_types=1);

final class Pasarela
{
    /** Valida los datos de la tarjeta. @return array<string,string> errores por campo */
    public static function validarTarjeta(string $numero, string $vence, string $cvv, string $titular): array
    {
        $e = [];
        $n = preg_replace('/\D/', '', $numero);
        if (!preg_match('/^\d{13,19}$/', $n) || !self::luhn($n)) {
            $e['numero'] = 'El número de tarjeta no es válido.';
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $vence, $m)) {
            $e['vence'] = 'Use el formato MM/AA.';
        } elseif (mktime(23, 59, 59, (int) $m[1] + 1, 0, 2000 + (int) $m[2]) < time()) {
            $e['vence'] = 'La tarjeta está vencida.';
        }
        $marca = self::marca($n);
        if (!preg_match($marca === 'American Express' ? '/^\d{4}$/' : '/^\d{3}$/', $cvv)) {
            $e['cvv'] = 'Código de seguridad no válido.';
        }
        if (!preg_match("/^[\\p{L} '.-]{3,80}$/u", $titular)) {
            $e['titular'] = 'Escriba el nombre como figura en la tarjeta.';
        }
        return $e;
    }

    /** Respuesta simulada del procesador: [estado, referencia, detalle, marca, últimos 4]. */
    public static function cobrarTarjeta(string $numero): array
    {
        $n = preg_replace('/\D/', '', $numero);
        $ultimos = substr($n, -4);
        $marca = self::marca($n);
        return match ($ultimos) {
            '0002'  => ['RECHAZADO', self::referencia('REC'), 'Fondos insuficientes (simulado)', $marca, $ultimos],
            '0069'  => ['RECHAZADO', self::referencia('REC'), 'Tarjeta reportada como vencida (simulado)', $marca, $ultimos],
            default => ['APROBADO', self::referencia('AUT'), 'Aprobado (modo demostración)', $marca, $ultimos],
        };
    }

    public static function referencia(string $prefijo): string
    {
        return $prefijo . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public static function marca(string $n): string
    {
        return match (true) {
            (bool) preg_match('/^4/', $n)               => 'Visa',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $n) => 'Mastercard',
            (bool) preg_match('/^3[47]/', $n)           => 'American Express',
            default                                     => 'Tarjeta',
        };
    }

    /** Algoritmo de Luhn (dígito verificador de las tarjetas). */
    public static function luhn(string $n): bool
    {
        $suma = 0;
        $doble = false;
        for ($i = strlen($n) - 1; $i >= 0; $i--) {
            $d = (int) $n[$i];
            if ($doble && ($d *= 2) > 9) {
                $d -= 9;
            }
            $suma += $d;
            $doble = !$doble;
        }
        return $suma % 10 === 0;
    }

    /** Envía el comprobante al correo del cliente si el SMTP está configurado. */
    public static function enviarComprobante(array $pedido, array $detalle): bool
    {
        if (!Mailer::configurado()) {
            return false;
        }
        $filas = '';
        $texto = '';
        foreach ($detalle as $d) {
            $filas .= '<tr><td style="padding:6px 0">' . (int) $d['cantidad'] . ' × ' . e($d['nombre']) . '</td><td style="text-align:right">' . e(bs($d['subtotal'])) . '</td></tr>';
            $texto .= (int) $d['cantidad'] . ' x ' . $d['nombre'] . ' — ' . bs($d['subtotal']) . "\n";
        }
        $url = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . '/mis-pedidos/ver?id=' . (int) $pedido['id'];
        $html = '<!doctype html><html><body style="font-family:Segoe UI,Arial,sans-serif;background:#f7f2ec;padding:24px;color:#2b211c">'
            . '<div style="max-width:520px;margin:auto;background:#fff;border-radius:14px;padding:24px;border:1px solid #eadfd4">'
            . '<h2 style="margin:0 0 4px">¡Gracias por tu compra!</h2><p style="color:#6b5a4e;margin:0 0 16px">Comprobante del pedido <b>' . e($pedido['numero']) . '</b></p>'
            . '<table style="width:100%;border-collapse:collapse">' . $filas
            . '<tr><td style="border-top:1px solid #eadfd4;padding-top:8px"><b>Total</b></td><td style="border-top:1px solid #eadfd4;text-align:right"><b>' . e(bs($pedido['total'])) . '</b></td></tr></table>'
            . '<p style="margin:16px 0 0"><a href="' . e($url) . '" style="color:#a8552f">Ver mi comprobante</a></p></div></body></html>';
        try {
            Mailer::enviar($pedido['correo'], 'Comprobante de tu pedido ' . $pedido['numero'] . ' · Taller Mitsü', $html,
                "Gracias por tu compra en Taller Mitsü.\nPedido {$pedido['numero']}\n\n$texto\nTotal: " . bs($pedido['total']) . "\n$url");
            Pedido::marcarComprobanteEnviado((int) $pedido['id']);
            return true;
        } catch (Throwable $e) {
            error_log('[Mitsu] No se pudo enviar el comprobante: ' . $e->getMessage());
            return false;
        }
    }
}
