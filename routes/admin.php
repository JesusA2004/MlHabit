<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HomeSettingsController;
use App\Http\Controllers\Admin\MediaCollectionController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageEntryController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SocialLinkController;
use App\Http\Controllers\Admin\StorageController;
use App\Http\Controllers\Admin\StudioController;
use App\Http\Controllers\Admin\UploadSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // MR. INSIGHT Studio — constructor visual
    Route::get('studio', [StudioController::class, 'index'])->name('studio.index');
    Route::post('studio', [StudioController::class, 'store'])->name('studio.store');
    Route::get('studio/{page}', [StudioController::class, 'edit'])->name('studio.edit');
    Route::delete('studio/{page}', [StudioController::class, 'destroy'])->name('studio.destroy');
    Route::get('studio/{page}/canvas', [StudioController::class, 'canvas'])->name('studio.canvas');
    Route::get('studio/{page}/vista-previa', [StudioController::class, 'preview'])->name('studio.preview');
    Route::put('studio/{page}/borrador', [StudioController::class, 'saveDraft'])->name('studio.borrador');
    Route::post('studio/{page}/resolver', [StudioController::class, 'resolve'])->name('studio.resolver');
    Route::post('studio/{page}/publicar', [StudioController::class, 'publish'])->name('studio.publicar');
    Route::post('studio/{page}/descartar', [StudioController::class, 'discard'])->name('studio.descartar');
    Route::post('studio/{page}/compartir', [StudioController::class, 'share'])->name('studio.compartir');
    Route::put('studio/{page}/ajustes', [StudioController::class, 'updateSettings'])->name('studio.ajustes');
    Route::get('studio/{page}/versiones/{version}', [StudioController::class, 'version'])->name('studio.version');
    Route::post('studio/{page}/versiones/{version}/restaurar', [StudioController::class, 'restore'])->name('studio.restaurar');

    Route::get('calendario', CalendarController::class)->name('calendario');

    Route::get('multimedia/buscar', [MediaController::class, 'search'])->name('multimedia.buscar');
    Route::get('multimedia/{media}/uso', [MediaController::class, 'usage'])->name('multimedia.uso');
    Route::resource('multimedia', MediaController::class)
        ->parameters(['multimedia' => 'media'])
        ->except(['show', 'edit', 'create']);

    Route::get('multimedia-colecciones', [MediaCollectionController::class, 'index'])->name('multimedia-colecciones.index');
    Route::post('multimedia-colecciones', [MediaCollectionController::class, 'store'])->name('multimedia-colecciones.store');

    Route::post('multimedia-sesiones', [UploadSessionController::class, 'store'])->name('multimedia-sesiones.store');
    Route::get('multimedia-sesiones/{uuid}', [UploadSessionController::class, 'show'])->name('multimedia-sesiones.show');
    Route::post('multimedia-sesiones/{uuid}/fragmentos', [UploadSessionController::class, 'appendChunk'])->name('multimedia-sesiones.fragmentos');
    Route::post('multimedia-sesiones/{uuid}/completar', [UploadSessionController::class, 'complete'])->name('multimedia-sesiones.completar');
    Route::delete('multimedia-sesiones/{uuid}', [UploadSessionController::class, 'destroy'])->name('multimedia-sesiones.destroy');

    Route::get('inicio', [HomeSettingsController::class, 'edit'])->name('inicio.edit');
    Route::put('inicio', [HomeSettingsController::class, 'update'])->name('inicio.update');
    Route::post('inicio/destacados', [HomeSettingsController::class, 'setFeatured'])->name('inicio.destacados');

    Route::get('apariencia', [AppearanceController::class, 'edit'])->name('apariencia.edit');
    Route::put('apariencia', [AppearanceController::class, 'update'])->name('apariencia.update');
    Route::post('apariencia/publicar', [AppearanceController::class, 'publish'])->name('apariencia.publicar');
    Route::post('apariencia/descartar', [AppearanceController::class, 'discard'])->name('apariencia.descartar');
    Route::post('apariencia/restablecer', [AppearanceController::class, 'reset'])->name('apariencia.restablecer');

    Route::get('almacenamiento', [StorageController::class, 'index'])->name('almacenamiento.index');
    Route::put('almacenamiento/{provider}', [StorageController::class, 'update'])->name('almacenamiento.update');
    Route::post('almacenamiento/{provider}/activar', [StorageController::class, 'activate'])->name('almacenamiento.activar');
    Route::post('almacenamiento/{provider}/probar', [StorageController::class, 'test'])->name('almacenamiento.probar');

    Route::get('estadisticas', AnalyticsController::class)->name('estadisticas');

    Route::get('actividad', [ActivityController::class, 'index'])->name('actividad.index');

    Route::get('paginas', [PageController::class, 'index'])->name('paginas.index');
    Route::get('paginas/{key}', [PageController::class, 'edit'])->name('paginas.edit');
    Route::put('paginas/{key}', [PageController::class, 'update'])->name('paginas.update');

    Route::post('paginas-entradas', [PageEntryController::class, 'store'])->name('paginas-entradas.store');
    Route::put('paginas-entradas/{pageEntry}', [PageEntryController::class, 'update'])->name('paginas-entradas.update');
    Route::delete('paginas-entradas/{pageEntry}', [PageEntryController::class, 'destroy'])->name('paginas-entradas.destroy');

    Route::resource('redes-sociales', SocialLinkController::class)
        ->parameters(['redes-sociales' => 'socialLink'])
        ->except(['show', 'create', 'edit']);

    Route::resource('categorias', CategoryController::class)
        ->parameters(['categorias' => 'category'])
        ->except(['show']);

    Route::resource('publicaciones', PostController::class)
        ->parameters(['publicaciones' => 'post'])
        ->except(['show']);

    Route::get('publicaciones/{post}/vista-previa', [PostController::class, 'preview'])->name('publicaciones.preview');
    Route::patch('publicaciones/{post}/autoguardar', [PostController::class, 'autosave'])->name('publicaciones.autoguardar');
    Route::post('publicaciones/{post}/publicar', [PostController::class, 'publish'])->name('publicaciones.publicar');
    Route::post('publicaciones/{post}/programar', [PostController::class, 'schedule'])->name('publicaciones.programar');
    Route::post('publicaciones/{post}/archivar', [PostController::class, 'archive'])->name('publicaciones.archivar');
    Route::post('publicaciones/{post}/destacar', [PostController::class, 'toggleFeatured'])->name('publicaciones.destacar');
});
