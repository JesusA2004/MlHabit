<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\TestimonialRequest;
use App\Models\Testimonial;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Testimonios (pestaña de Sitio web). Publicar exige `content.publish` y
 * consentimiento registrado (validado aquí y en el modelo).
 */
class TestimonialController extends Controller
{
    public function store(TestimonialRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Testimonial::class);

        $data = $request->validated();
        if (($data['is_published'] ?? false) === true) {
            Gate::authorize('publish', Testimonial::class);
        }

        $data['sort'] = (int) Testimonial::query()->max('sort') + 1;
        $testimonial = Testimonial::query()->create($data);

        $activity->log('sitio.testimonio_creado', $testimonial, [
            'publicado' => $testimonial->is_published,
            'consentimiento' => $testimonial->consent_obtained_at?->toDateString(),
        ]);

        return back()->with('success', 'Testimonio guardado.');
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $testimonial);

        $data = $request->validated();
        $wantsPublished = (bool) ($data['is_published'] ?? false);
        if ($wantsPublished !== $testimonial->is_published || ($testimonial->is_published && ! Gate::allows('publish', Testimonial::class))) {
            Gate::authorize('publish', Testimonial::class);
        }

        $testimonial->fill($data);
        $activity->logModelChanges('sitio.testimonio_actualizado', $testimonial);
        $testimonial->save();

        return back()->with('success', 'Testimonio actualizado.');
    }

    public function destroy(Testimonial $testimonial, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $testimonial);

        $testimonial->delete();
        $activity->log('sitio.testimonio_eliminado', $testimonial, ['autor' => $testimonial->author_name]);

        return back()->with('success', 'Testimonio eliminado.');
    }
}
