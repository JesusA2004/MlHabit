<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de subida
    |--------------------------------------------------------------------------
    | `max_upload_mb` aplica a video y archivos subidos por fragmentos;
    | `max_image_mb` y `max_document_mb` a imágenes y PDF.
    */
    'max_upload_mb' => (int) env('MEDIA_MAX_UPLOAD_MB', 2048),
    'max_image_mb' => (int) env('MEDIA_MAX_IMAGE_MB', 40),
    'max_document_mb' => (int) env('MEDIA_MAX_DOCUMENT_MB', 25),
    'chunk_size_mb' => (int) env('MEDIA_CHUNK_SIZE_MB', 10),

    /*
    |--------------------------------------------------------------------------
    | Tipos permitidos (lista blanca)
    |--------------------------------------------------------------------------
    | Extensión → MIME reales aceptados (detectados por contenido con finfo,
    | nunca por la extensión ni por lo que declare el navegador).
    */
    'allowed' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif', 'image/heif'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf'],
        'mp4' => ['video/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Imágenes responsivas
    |--------------------------------------------------------------------------
    | Se generan variantes WebP (480/960/1600/2400 px) y, si GD lo soporta y
    | está habilitado, AVIF con los mismos anchos.
    */
    'avif' => (bool) env('MEDIA_AVIF', true),
    'webp_quality' => 80,
    'avif_quality' => 55,

    /* Horas antes de purgar archivos privados que nunca se vincularon. */
    'orphan_hours' => (int) env('MEDIA_ORPHAN_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Procesamiento de video (FFmpeg)
    |--------------------------------------------------------------------------
    |
    | Si estas rutas están vacías o el binario no existe/no es ejecutable,
    | el pipeline de video se degrada: acepta la subida pero no genera
    | miniatura ni duración automáticas (ver FfmpegService).
    |
    */
    'ffmpeg_path' => env('FFMPEG_BINARY_PATH'),
    'ffprobe_path' => env('FFPROBE_BINARY_PATH'),

];
