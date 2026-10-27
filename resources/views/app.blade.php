@php
    $component = $page['component'] ?? '';
    $seo = is_array($page['props']['seo'] ?? null) ? $page['props']['seo'] : null;
    $appName = \App\Services\Web\PublicSite::name();

    // Etiquetas SEO/Open Graph escritas en el servidor (sin depender de JS):
    // las páginas públicas las traen en `seo.head`; el resto (panel, portal,
    // acceso, técnicos) no se indexa.
    $headTags = $seo !== null && is_array($seo['head'] ?? null) ? $seo['head'] : [];
    $documentTitle = $seo !== null && is_string($seo['fullTitle'] ?? null) && $seo['fullTitle'] !== ''
        ? $seo['fullTitle']
        : $appName;

    $siteVerification = $seo !== null ? \App\Support\Settings::get('seo.google_site_verification') : null;
    $bingVerification = $seo !== null ? \App\Support\Settings::get('seo.bing_site_verification') : null;

    // Fuentes de marca autoalojadas (latin, variables) para precargar.
    $fontPreloads = [];
    foreach (['resources/fonts/web/figtree-latin.woff2', 'resources/fonts/web/familjen-grotesk-latin.woff2'] as $font) {
        try {
            $fontPreloads[] = Vite::asset($font);
        } catch (\Throwable) {
            // Sin compilación (pruebas) no hay manifiesto: se omite la precarga.
        }
    }
@endphp
<!DOCTYPE html>
<html lang="es-MX">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#F7F5F2">
        <meta name="color-scheme" content="light">
        <meta name="format-detection" content="telephone=no">
        @if (is_string($siteVerification) && $siteVerification !== '')
            <meta name="google-site-verification" content="{{ $siteVerification }}">
        @endif
        @if (is_string($bingVerification) && $bingVerification !== '')
            <meta name="msvalidate.01" content="{{ $bingVerification }}">
        @endif

        <style>
            html { background-color: #f7f5f2; color-scheme: light; }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#2F5A4C">
        <link rel="manifest" href="/site.webmanifest">

        @foreach ($fontPreloads as $fontUrl)
            @if ($fontUrl !== '')
                <link rel="preload" href="{{ $fontUrl }}" as="font" type="font/woff2" crossorigin>
            @endif
        @endforeach

        @if ($seo === null)
            {{-- Panel, portal, acceso, técnicos: nunca se indexan (también con SSR activo). --}}
            <meta name="robots" content="noindex, nofollow" data-inertia="robots">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$component}.vue"])
        <x-inertia::head>
            <title data-inertia="">{{ $documentTitle }}</title>
            @foreach ($headTags as $tag)
                {!! $tag !!}
            @endforeach
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
