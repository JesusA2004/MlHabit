<?php

use App\Http\Controllers\Admin\Web\AnalyticsController;
use App\Http\Controllers\Admin\Web\CatalogController;
use App\Http\Controllers\Admin\Web\FaqController;
use App\Http\Controllers\Admin\Web\MediaCollectionController;
use App\Http\Controllers\Admin\Web\MediaController;
use App\Http\Controllers\Admin\Web\PageController;
use App\Http\Controllers\Admin\Web\ProductController;
use App\Http\Controllers\Admin\Web\ShowcaseController;
use App\Http\Controllers\Admin\Web\SiteBuilderController;
use App\Http\Controllers\Admin\Web\SiteController;
use App\Http\Controllers\Admin\Web\SocialLinkController;
use App\Http\Controllers\Admin\Web\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel → Contenido (módulo web)
|--------------------------------------------------------------------------
| Sitio web (CMS), catálogo, inspiración, preguntas, multimedia y
| estadísticas del sitio. Cada acción autoriza con Policies/Gates.
*/

Route::middleware(['auth', 'verified', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    // Sitio web
    Route::get('sitio', [SiteController::class, 'index'])->name('sitio.index');

    // Editor visual de la portada (borrador → publicar, con historial)
    Route::get('sitio/editor', [SiteBuilderController::class, 'edit'])->name('sitio.editor.edit');
    Route::put('sitio/editor/borrador', [SiteBuilderController::class, 'saveDraft'])->middleware('throttle:240,1')->name('sitio.editor.borrador');
    Route::post('sitio/editor/publicar', [SiteBuilderController::class, 'publish'])->name('sitio.editor.publicar');
    Route::post('sitio/editor/descartar', [SiteBuilderController::class, 'discard'])->name('sitio.editor.descartar');
    Route::post('sitio/editor/versiones/{version}/restaurar', [SiteBuilderController::class, 'restore'])->name('sitio.editor.versiones.restaurar');
    Route::get('sitio/editor/vista-previa', [SiteBuilderController::class, 'preview'])->middleware('signed')->name('sitio.editor.vista-previa');

    Route::put('sitio/ajustes/{group}', [SiteController::class, 'updateSettings'])->whereIn('group', ['brand', 'contact', 'seo', 'footer'])->name('sitio.ajustes.update');

    Route::post('sitio/redes', [SocialLinkController::class, 'store'])->name('sitio.redes.store');
    Route::put('sitio/redes-orden', [SocialLinkController::class, 'reorder'])->name('sitio.redes.orden');
    Route::put('sitio/redes/{socialLink}', [SocialLinkController::class, 'update'])->name('sitio.redes.update');
    Route::delete('sitio/redes/{socialLink}', [SocialLinkController::class, 'destroy'])->name('sitio.redes.destroy');

    Route::post('sitio/testimonios', [TestimonialController::class, 'store'])->name('sitio.testimonios.store');
    Route::put('sitio/testimonios/{testimonial}', [TestimonialController::class, 'update'])->name('sitio.testimonios.update');
    Route::delete('sitio/testimonios/{testimonial}', [TestimonialController::class, 'destroy'])->name('sitio.testimonios.destroy');

    Route::get('sitio/paginas/crear', [PageController::class, 'create'])->name('sitio.paginas.create');
    Route::post('sitio/paginas', [PageController::class, 'store'])->name('sitio.paginas.store');
    Route::get('sitio/paginas/{page}/editar', [PageController::class, 'edit'])->name('sitio.paginas.edit');
    Route::put('sitio/paginas/{page}', [PageController::class, 'update'])->name('sitio.paginas.update');
    Route::post('sitio/paginas/{page}/publicar', [PageController::class, 'publish'])->name('sitio.paginas.publicar');
    Route::post('sitio/paginas/{page}/despublicar', [PageController::class, 'unpublish'])->name('sitio.paginas.despublicar');
    Route::delete('sitio/paginas/{page}', [PageController::class, 'destroy'])->name('sitio.paginas.destroy');

    // Catálogo
    Route::get('catalogo', [CatalogController::class, 'index'])->name('catalogo.index');
    Route::post('catalogo/categorias', [CatalogController::class, 'storeCategory'])->name('catalogo.categorias.store');
    Route::put('catalogo/categorias-orden', [CatalogController::class, 'reorderCategories'])->name('catalogo.categorias.orden');
    Route::put('catalogo/categorias/{category}', [CatalogController::class, 'updateCategory'])->name('catalogo.categorias.update');
    Route::delete('catalogo/categorias/{category}', [CatalogController::class, 'destroyCategory'])->name('catalogo.categorias.destroy');
    Route::post('catalogo/acabados', [CatalogController::class, 'storeFinish'])->name('catalogo.acabados.store');
    Route::put('catalogo/acabados/{finish}', [CatalogController::class, 'updateFinish'])->name('catalogo.acabados.update');
    Route::delete('catalogo/acabados/{finish}', [CatalogController::class, 'destroyFinish'])->name('catalogo.acabados.destroy');

    Route::get('catalogo/productos/crear', [ProductController::class, 'create'])->name('catalogo.productos.create');
    Route::post('catalogo/productos', [ProductController::class, 'store'])->name('catalogo.productos.store');
    Route::put('catalogo/productos-orden', [ProductController::class, 'reorder'])->name('catalogo.productos.orden');
    Route::get('catalogo/productos/{product}/editar', [ProductController::class, 'edit'])->name('catalogo.productos.edit');
    Route::put('catalogo/productos/{product}', [ProductController::class, 'update'])->name('catalogo.productos.update');
    Route::patch('catalogo/productos/{product}/estado', [ProductController::class, 'updateStatus'])->name('catalogo.productos.estado');
    Route::delete('catalogo/productos/{product}', [ProductController::class, 'destroy'])->name('catalogo.productos.destroy');

    // Inspiración
    Route::get('inspiracion', [ShowcaseController::class, 'index'])->name('inspiracion.index');
    Route::get('inspiracion/crear', [ShowcaseController::class, 'create'])->name('inspiracion.create');
    Route::post('inspiracion', [ShowcaseController::class, 'store'])->name('inspiracion.store');
    Route::get('inspiracion/{showcase}/editar', [ShowcaseController::class, 'edit'])->name('inspiracion.edit');
    Route::put('inspiracion/{showcase}', [ShowcaseController::class, 'update'])->name('inspiracion.update');
    Route::patch('inspiracion/{showcase}/estado', [ShowcaseController::class, 'updateStatus'])->name('inspiracion.estado');
    Route::delete('inspiracion/{showcase}', [ShowcaseController::class, 'destroy'])->name('inspiracion.destroy');

    // Preguntas frecuentes
    Route::get('preguntas', [FaqController::class, 'index'])->name('preguntas.index');
    Route::post('preguntas', [FaqController::class, 'store'])->name('preguntas.store');
    Route::put('preguntas-orden', [FaqController::class, 'reorder'])->name('preguntas.orden');
    Route::put('preguntas/{faq}', [FaqController::class, 'update'])->name('preguntas.update');
    Route::delete('preguntas/{faq}', [FaqController::class, 'destroy'])->name('preguntas.destroy');

    // Multimedia
    Route::get('multimedia', [MediaController::class, 'index'])->name('multimedia.index');
    Route::get('multimedia/buscar', [MediaController::class, 'search'])->name('multimedia.buscar');
    Route::post('multimedia', [MediaController::class, 'store'])->middleware('throttle:120,1')->name('multimedia.store');
    Route::patch('multimedia/{media}', [MediaController::class, 'update'])->name('multimedia.update');
    Route::post('multimedia/{media}/reemplazar', [MediaController::class, 'replace'])->name('multimedia.reemplazar');
    Route::get('multimedia/{media}/usos', [MediaController::class, 'usage'])->name('multimedia.usos');
    Route::delete('multimedia/{media}', [MediaController::class, 'destroy'])->name('multimedia.destroy');
    Route::get('multimedia-colecciones', [MediaCollectionController::class, 'index'])->name('multimedia-colecciones.index');
    Route::post('multimedia-colecciones', [MediaCollectionController::class, 'store'])->name('multimedia-colecciones.store');

    // Estadísticas del sitio
    Route::get('estadisticas', AnalyticsController::class)->name('estadisticas');
});
