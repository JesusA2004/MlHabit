<?php

/*
|--------------------------------------------------------------------------
| ML Habitat — cabeceras de seguridad
|--------------------------------------------------------------------------
| Las aplica App\Http\Middleware\SecurityHeaders a todo el grupo `web`.
|
| CSP: por defecto se envía en modo *Report-Only* (CSP_ENFORCE=false) para
| no romper Openpay (openpay.js + openpay-data.js de antifraude) ni
| scripts en línea mientras se verifica en sandbox. Cuando el navegador no
| reporte violaciones en el flujo completo de pago, pon CSP_ENFORCE=true.
*/

return [

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        'enforce' => (bool) env('CSP_ENFORCE', false),
        'report_uri' => env('CSP_REPORT_URI'),

        // Orígenes adicionales permitidos por directiva (fuentes, pagos…).
        'sources' => [
            'script-src' => [
                'https://js.openpay.mx',
                'https://resources.openpay.mx',
            ],
            'style-src' => [
                'https://fonts.bunny.net',
                'https://fonts.googleapis.com',
            ],
            'font-src' => [
                'https://fonts.bunny.net',
                'https://fonts.gstatic.com',
            ],
            'img-src' => [
                'https://*.openpay.mx',
            ],
            'connect-src' => [
                'https://api.openpay.mx',
                'https://sandbox-api.openpay.mx',
                'https://*.openpay.mx',
            ],
            'frame-src' => [
                'https://*.openpay.mx',
            ],
        ],
    ],

    'hsts' => [
        // Solo se envía en producción y sobre HTTPS.
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
    ],

    // camera=(self): la app de técnicos toma fotos de evidencia.
    'permissions_policy' => 'camera=(self), geolocation=(), microphone=(), payment=(), usb=()',

];
