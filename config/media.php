<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de subida
    |--------------------------------------------------------------------------
    */
    'max_upload_mb' => (int) env('MEDIA_MAX_UPLOAD_MB', 2048),
    'chunk_size_mb' => (int) env('MEDIA_CHUNK_SIZE_MB', 10),

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
