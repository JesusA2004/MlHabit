<?php

use App\Http\Controllers\Files\PrivateMediaController;
use App\Http\Controllers\Files\UploadSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Archivos privados y subidas (módulo web)
|--------------------------------------------------------------------------
| /archivos/{uuid}: descarga autorizada de archivos privados.
| /subidas: subidas reanudables por fragmentos (useChunkedUpload.ts).
| Público → requiere `media.manage`; privado → cualquier usuario activo.
*/

Route::middleware('auth')->group(function () {
    Route::get('archivos/{media:uuid}', PrivateMediaController::class)
        ->middleware('throttle:240,1')
        ->name('archivos.show');

    Route::prefix('subidas')->name('subidas.')->middleware('throttle:600,1')->group(function () {
        Route::post('/', [UploadSessionController::class, 'store'])->name('store');
        Route::get('{uuid}', [UploadSessionController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::post('{uuid}/fragmentos', [UploadSessionController::class, 'appendChunk'])->whereUuid('uuid')->name('fragmentos');
        Route::post('{uuid}/completar', [UploadSessionController::class, 'complete'])->whereUuid('uuid')->name('completar');
        Route::delete('{uuid}', [UploadSessionController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    });
});
