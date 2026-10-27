<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Openpay México (módulo visits / pagos)
    |--------------------------------------------------------------------------
    | Ver docs/pagos/OPENPAY-INTEGRACION.md. Al frontend solo se comparten
    | merchant_id, public_key y el modo sandbox. La llave privada y las
    | credenciales del webhook NUNCA salen del backend.
    */
    'openpay' => [
        'merchant_id' => env('OPENPAY_MERCHANT_ID'),
        'public_key' => env('OPENPAY_PUBLIC_KEY'),
        'private_key' => env('OPENPAY_PRIVATE_KEY'),
        'production' => filter_var(env('OPENPAY_PRODUCTION', false), FILTER_VALIDATE_BOOL),
        'webhook_user' => env('OPENPAY_WEBHOOK_USER'),
        'webhook_password' => env('OPENPAY_WEBHOOK_PASSWORD'),
        // always | auto (autenticación selectiva: 3DS solo si el antifraude rechaza con 3005) | never
        '3ds_mode' => env('OPENPAY_3DS_MODE', 'auto'),
        'timeout' => (int) env('OPENPAY_TIMEOUT', 30),
        'connect_timeout' => 10,
        // Un intento sin respuesta concluyente que tampoco aparece en Openpay
        // tras este margen se da por no registrado.
        'unknown_grace_seconds' => 120,
        // Un 3D Secure sin completar tras estos minutos se considera abandonado.
        'pending_3ds_minutes' => 30,
        // SOLO pruebas E2E: con APP_ENV=e2e usa App\Payments\Testing\E2eSandboxGateway
        // (ver App\Providers\E2eServiceProvider). Ignorado en cualquier otro entorno.
        'e2e_fake' => filter_var(env('OPENPAY_E2E_FAKE', false), FILTER_VALIDATE_BOOL),
    ],

];
