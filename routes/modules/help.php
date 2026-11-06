<?php

use App\Http\Controllers\Help\TourProgressController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ayuda: centro de ayuda del panel y avance de los recorridos guiados.
|--------------------------------------------------------------------------
| El avance es por persona (panel, portal y app de técnicos) y responde
| JSON. El centro de ayuda del portal es /portal/ayuda (módulo portal).
*/

Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('ayuda', 'admin/ayuda/Index')->name('ayuda.index');
    });

Route::middleware(['auth', 'throttle:60,1'])
    ->prefix('recorridos')
    ->name('recorridos.')
    ->group(function () {
        Route::get('/', [TourProgressController::class, 'index'])->name('index');
        Route::put('{tour}', [TourProgressController::class, 'update'])
            ->where('tour', '[a-z0-9][a-z0-9.\-]{0,79}')
            ->name('update');
        Route::delete('/', [TourProgressController::class, 'destroy'])->name('destroy');
    });
