<?php

namespace App\Http\Controllers\Public;

use App\Enums\FaqTopic;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\Web\OgImage;
use App\Services\Web\Presenter;
use App\Services\Web\PublicPage;
use App\Services\Web\Seo;
use Inertia\Response;

/**
 * Preguntas frecuentes agrupadas por tema (FAQPage en JSON-LD).
 */
class FaqPageController extends Controller
{
    public function __invoke(Presenter $presenter, PublicPage $page, Seo $seo, OgImage $og): Response
    {
        $faqs = Faq::query()->published()->ordered()->get();

        $groups = [];
        foreach (FaqTopic::cases() as $topic) {
            $items = $faqs->filter(fn (Faq $f) => $f->topic === $topic)->values();
            if ($items->isNotEmpty()) {
                $groups[] = [
                    'topic' => $topic->value,
                    'label' => $topic->label(),
                    'items' => $items->map(fn (Faq $f) => $presenter->faq($f))->all(),
                ];
            }
        }

        return $page->render('public/preguntas/Index', [
            'groups' => $groups,
        ], $seo->page(
            'Preguntas frecuentes',
            'Respuestas sobre el proceso, la visita técnica, los pagos, la garantía y nuestros muebles a la medida.',
            jsonLd: array_values(array_filter([
                $seo->faqPage($faqs),
                $seo->breadcrumbs([['Inicio', '/'], ['Preguntas frecuentes', '/preguntas-frecuentes']]),
            ])),
            canonical: url('/preguntas-frecuentes'),
            og: $og->page('preguntas-frecuentes', 'Preguntas frecuentes', 'Proceso, visita, pagos y garantía'),
        ));
    }
}
