<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use App\Models\User;
use App\Services\Web\HomePage;
use App\Services\Web\SiteBuilder;
use App\Services\Web\SitePages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas propias publicadas en `/{slug}` (ruta de respaldo: se evalúa
 * después de todas las demás, nunca tapa una ruta del sitio).
 *
 * Sin publicar responde 404, salvo para el equipo con `content.manage`, que
 * ve el borrador como vista previa (sin indexar).
 */
class SitePageController extends Controller
{
    public function __invoke(Request $request, string $slug, HomePage $home, SiteBuilder $builder, SitePages $pages): Response
    {
        $sitePage = SitePage::query()->with('ogMedia.variants')->where('slug', $slug)->first();
        abort_if($sitePage === null, 404);

        if (! $sitePage->is_published) {
            $user = $request->user();
            abort_unless($user instanceof User && $user->hasPermission('content.manage'), 404);

            $draft = $builder->draft($sitePage->pageKey());
            $response = $pages->render($sitePage, $home->resolve($draft->snapshot['sections']), preview: true)->toResponse($request);
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }

        return $pages->render($sitePage, $home->sections(page: $sitePage->pageKey()))->toResponse($request);
    }
}
