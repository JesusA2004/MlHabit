<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\FaqTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FaqRequest;
use App\Models\Faq;
use App\Models\ProductCategory;
use App\Services\ActivityLogger;
use App\Services\Web\PublicSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Preguntas frecuentes, agrupadas por tema y ordenables.
 * Sin `content.publish` las preguntas nuevas quedan como borrador.
 */
class FaqController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Faq::class);

        $faqs = Faq::query()->with('category:id,name')->orderBy('topic')->ordered()->get();

        return Inertia::render('admin/preguntas/Index', [
            'faqs' => $faqs->map(fn (Faq $f) => [
                'id' => $f->id,
                'topic' => $f->topic->value,
                'topicLabel' => $f->topic->label(),
                'productCategoryId' => $f->product_category_id,
                'categoryName' => $f->category?->name,
                'question' => $f->question,
                'answer' => $f->answer,
                'isPublished' => $f->is_published,
                'sort' => $f->sort,
            ])->values(),
            'topics' => FaqTopic::options(),
            'categories' => ProductCategory::query()->ordered()->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'can' => ['publish' => Gate::allows('publish', Faq::class)],
        ]);
    }

    public function store(FaqRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Faq::class);

        $data = $request->validated();
        $canPublish = Gate::allows('publish', Faq::class);
        $faq = Faq::query()->create([
            ...$data,
            'is_published' => $canPublish && (bool) ($data['is_published'] ?? false),
            'sort' => (int) Faq::query()->where('topic', $data['topic'])->max('sort') + 10,
        ]);

        $activity->log('preguntas.creada', $faq, ['pregunta' => $faq->question]);

        return back()->with('success', $faq->is_published ? 'Pregunta publicada.' : 'Pregunta guardada como borrador.');
    }

    public function update(FaqRequest $request, Faq $faq, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $faq);

        $data = $request->validated();
        $wants = (bool) ($data['is_published'] ?? $faq->is_published);
        if ($wants !== $faq->is_published || $faq->is_published) {
            Gate::authorize('publish', Faq::class);
        }

        $faq->fill([...$data, 'is_published' => $wants]);
        $activity->logModelChanges('preguntas.actualizada', $faq);
        $faq->save();

        return back()->with('success', 'Pregunta actualizada.');
    }

    public function reorder(Request $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Faq::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:faqs,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $index => $id) {
                Faq::query()->whereKey($id)->update(['sort' => ($index + 1) * 10]);
            }
        });
        PublicSite::flush();
        $activity->log('preguntas.reordenadas', null, ['ids' => $data['ids']]);

        return back()->with('success', 'Orden actualizado.');
    }

    public function destroy(Faq $faq, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $faq);
        if ($faq->is_published) {
            Gate::authorize('publish', Faq::class);
        }

        $faq->delete();
        $activity->log('preguntas.eliminada', $faq, ['pregunta' => $faq->question]);

        return back()->with('success', 'Pregunta eliminada.');
    }
}
