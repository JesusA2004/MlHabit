<?php

use App\Http\Controllers\Public\MediaStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ML Habitat — rutas web
|--------------------------------------------------------------------------
| Cada módulo vive en su propio archivo bajo routes/modules para mantener
| las responsabilidades separadas. Orden: público → portal → técnico →
| administración → ajustes de cuenta.
*/

Route::match(['get', 'head'], 'media/{path}', MediaStreamController::class)
    ->where('path', '.*')
    ->name('media.stream');

foreach (glob(__DIR__.'/modules/*.php') ?: [] as $file) {
    require $file;
}

require __DIR__.'/settings.php';
