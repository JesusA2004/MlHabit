<?php

use App\Http\Controllers\Public\CatalogController;
use App\Http\Controllers\Public\FaqPageController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\InspirationController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público (módulo web)
|--------------------------------------------------------------------------
| Portada, catálogo, inspiración, preguntas, páginas legales y SEO técnico.
| `home` conserva su nombre: otras partes de la app lo usan.
*/

Route::get('/', HomeController::class)->name('home');

Route::name('public.')->group(function () {
    Route::get('catalogo', [CatalogController::class, 'index'])->name('catalogo.index');
    Route::get('catalogo/{category:slug}', [CatalogController::class, 'category'])->name('catalogo.categoria');
    Route::get('catalogo/{category:slug}/{product:slug}', [CatalogController::class, 'product'])->name('catalogo.producto');

    Route::get('inspiracion', [InspirationController::class, 'index'])->name('inspiracion.index');
    Route::get('inspiracion/{showcase:slug}', [InspirationController::class, 'show'])->name('inspiracion.show');

    Route::get('preguntas-frecuentes', FaqPageController::class)->name('preguntas');

    Route::get('legal/{page:slug}', LegalPageController::class)->name('legal');

    // Accesos cortos habituales a las páginas legales.
    // GET explícito (Route::redirect registra todos los verbos y Wayfinder no los tipa).
    Route::get('aviso-de-privacidad', fn () => redirect('/legal/aviso-de-privacidad', 301))->name('legal.privacidad');
    Route::get('terminos-y-condiciones', fn () => redirect('/legal/terminos-y-condiciones', 301))->name('legal.terminos');

    Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
});
