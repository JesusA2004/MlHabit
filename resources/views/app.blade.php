@php
    $component = $page['component'] ?? '';
    // El sitio público y el portal de clientes siempre se muestran en claro;
    // el modo oscuro aplica al panel, a técnicos y a la configuración.
    $forceLight = str_starts_with($component, 'public/') || str_starts_with($component, 'portal/');
    $appearanceValue = $forceLight ? 'light' : ($appearance ?? 'system');
@endphp
<!DOCTYPE html>
<html lang="es-MX" @class(['dark' => $appearanceValue === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#F7F5F2" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#141311" media="(prefers-color-scheme: dark)">
        <meta name="color-scheme" content="{{ $forceLight ? 'light' : 'light dark' }}">

        {{-- Aplica el modo oscuro del sistema antes de pintar para evitar parpadeos. --}}
        <script>
            (function () {
                var appearance = @json($appearanceValue);

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <style>
            html { background-color: #f7f5f2; }
            html.dark { background-color: #141311; }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=familjen-grotesk:500,600,700|figtree:400,500,600,700&display=swap">

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$component}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'ML Habitat') ?: 'ML Habitat' }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
