<?php

namespace App\Payments\Openpay;

/**
 * Traduce códigos de error de Openpay (docs/pagos/OPENPAY-INVESTIGACION.md §9)
 * a mensajes seguros en español. Nunca se muestra el `description` crudo del
 * proveedor. Para robo/fraude/pérdida/retención (3004, 3005, 3009, 3011) se usa
 * un mensaje genérico de rechazo: no se revela el motivo real.
 */
final class OpenpayErrorMap
{
    private const GENERIC_DECLINE = 'Tu banco no autorizó el cargo. Intenta con otra tarjeta o comunícate con tu banco.';

    private const GENERIC_ERROR = 'No pudimos procesar el pago en este momento. No se realizó ningún cargo; inténtalo de nuevo en unos minutos.';

    /** @var array<int, string> */
    private const MESSAGES = [
        1001 => 'Los datos del pago están incompletos. Revisa la información e inténtalo de nuevo.',
        1003 => 'Algún dato de la tarjeta no es válido. Revísalo e inténtalo de nuevo.',
        1012 => 'El monto está fuera de los límites permitidos para pagos con tarjeta. Contáctanos para otras formas de pago.',
        1018 => 'Se superó el número de intentos permitidos con esta tarjeta. Intenta con otra tarjeta.',
        1020 => 'El monto del cargo no es válido. Contáctanos para ayudarte.',
        1024 => 'El monto excede el límite permitido. Contáctanos para otras formas de pago.',
        2004 => 'El número de tarjeta no es válido. Revísalo e inténtalo de nuevo.',
        2005 => 'La tarjeta está vencida. Intenta con otra tarjeta.',
        2006 => 'Falta el código de seguridad (CVV) de la tarjeta.',
        2007 => 'Esa tarjeta es de prueba y no puede usarse para pagos reales.',
        2008 => 'La tarjeta no es válida para pagar con puntos.',
        2009 => 'El código de seguridad (CVV) no es válido.',
        2010 => 'No se completó la autenticación con tu banco (3D Secure). Inténtalo de nuevo o usa otra tarjeta.',
        2011 => 'Ese tipo de tarjeta no se acepta. Intenta con una tarjeta Visa o Mastercard.',
        3001 => self::GENERIC_DECLINE,
        3002 => 'La tarjeta está vencida. Intenta con otra tarjeta.',
        3003 => 'La tarjeta no tiene fondos suficientes. Intenta con otra tarjeta.',
        3004 => self::GENERIC_DECLINE,
        3005 => self::GENERIC_DECLINE,
        3006 => 'La operación no está permitida para esta tarjeta. Intenta con otra tarjeta.',
        3008 => 'Esta tarjeta no admite compras en línea. Intenta con otra tarjeta.',
        3009 => self::GENERIC_DECLINE,
        3010 => self::GENERIC_DECLINE,
        3011 => self::GENERIC_DECLINE,
        3012 => 'Tu banco requiere que autorices esta compra. Comunícate con tu banco e inténtalo de nuevo.',
    ];

    /** Errores de configuración/cuenta del comercio: no son culpa del cliente. */
    private const MERCHANT_ERRORS = [1002, 1008, 1010, 1013, 1014, 1023, 1025, 3201, 3203, 3204, 3205, 4001, 4002];

    public static function message(int|string|null $code): string
    {
        $code = (int) $code;

        if (in_array($code, self::MERCHANT_ERRORS, true)) {
            return 'No pudimos procesar pagos con tarjeta en este momento. No se realizó ningún cargo; inténtalo más tarde o contáctanos.';
        }

        return self::MESSAGES[$code] ?? self::GENERIC_ERROR;
    }

    /** Errores de servidor/gateway con resultado incierto (el cargo pudo crearse). */
    public static function isUncertain(int|string|null $code, ?int $httpStatus = null): bool
    {
        return in_array((int) $code, [1000, 1004, 1015, 1017], true)
            || ($httpStatus !== null && $httpStatus >= 500);
    }

    /** Rechazo del antifraude: candidato a autenticación selectiva con 3D Secure. */
    public static function isFraudDecline(int|string|null $code): bool
    {
        return (int) $code === 3005;
    }

    /** Código conocido de la pasarela (para mostrar en el panel interno). */
    public static function isMerchantError(int|string|null $code): bool
    {
        return in_array((int) $code, self::MERCHANT_ERRORS, true);
    }
}
