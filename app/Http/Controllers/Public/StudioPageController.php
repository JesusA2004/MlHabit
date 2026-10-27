<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\StudioPage;
use App\Services\PublicSiteDataService;
use App\Studio\StudioPagePresenter;
use App\Studio\StudioService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas personalizadas de Studio (/{slug}) y enlaces temporales de vista
 * previa firmados para compartir un borrador sin publicarlo.
 */
class StudioPageController extends Controller
{
    /**
     * Ruta fallback: solo responde si existe una página personalizada
     * publicada con ese slug; en cualquier otro caso, 404 editorial.
     */
    public function show(Request $request, PublicSiteDataService $siteData, StudioService $studio, StudioPagePresenter $presenter): Response
    {
        $slug = trim($request->path(), '/');
        abort_unless(preg_match('/^[a-z0-9-]{1,80}$/', $slug) === 1, 404);

        $page = StudioPage::query()
            ->where('kind', 'custom')
            ->where('slug', $slug)
            ->whereNotNull('published_version_id')
            ->first();
        abort_if($page === null, 404);

        $document = $studio->publishedDocument($page);
        abort_if($document === null, 404);

        return Inertia::render('public/StudioPage', [
            ...$siteData->base(),
            'studio' => $presenter->payload($page, $document),
        ]);
    }

    /**
     * Vista previa compartida mediante URL firmada y con caducidad
     * (generada en /admin/studio). Muestra el borrador vigente.
     */
    public function sharedPreview(StudioPage $page, PublicSiteDataService $siteData, StudioService $studio, StudioPagePresenter $presenter): \Symfony\Component\HttpFoundation\Response
    {
        $response = Inertia::render('public/StudioPage', [
            ...$siteData->base(),
            'studio' => $presenter->payload($page, $studio->draftDocument($page)),
            'previewing' => true,
        ])->toResponse(request());
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
