<?php

namespace App\Http\Controllers\Public\Crm;

use App\Enums\BudgetRange;
use App\Enums\PaymentPreference;
use App\Enums\PreferredContact;
use App\Enums\Timeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SubmitLeadRequest;
use App\Models\Lead;
use App\Models\Media;
use App\Services\CoverageService;
use App\Services\Crm\ConsultationSettings;
use App\Services\Crm\LeadIntakeService;
use App\Services\Web\OgImage;
use App\Services\Web\Seo;
use App\Support\Crm\Attribution;
use App\Support\Crm\CrmLinks;
use App\Support\Crm\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /solicitar: formulario progresivo para pedir una propuesta. Crea cliente
 * (deduplicado) + prospecto con folio y atribución.
 */
class LeadRequestController extends Controller
{
    public function create(Request $request): Response
    {
        $categories = self::categories();
        $preselected = collect($categories)->firstWhere('slug', (string) $request->query('categoria'));

        return Inertia::render('public/solicitar/Create', [
            'seo' => app(Seo::class)->page('Solicita tu propuesta a la medida', 'Cuéntanos qué mueble necesitas: cocina integral, clóset, recámara o centro de entretenimiento. Te contactamos para una asesoría sin compromiso.', canonical: url('/solicitar'), og: app(OgImage::class)->page('solicitar', 'Solicita tu propuesta a la medida', 'Asesoría sin compromiso')),
            'categories' => $categories,
            'preselected' => $preselected ? [$preselected['id']] : [],
            'options' => [
                'budgetRanges' => Options::of(BudgetRange::class),
                'paymentPreferences' => Options::of(PaymentPreference::class),
                'timeframes' => Options::of(Timeframe::class),
                'preferredContacts' => Options::of(PreferredContact::class),
            ],
            'attribution' => Attribution::resolve($request),
            'limits' => [
                'maxPhotos' => SubmitLeadRequest::MAX_PHOTOS,
                'maxPhotoMb' => (int) (SubmitLeadRequest::MAX_PHOTO_KB / 1024),
                'photoTypes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'privacyUrl' => url('/aviso-de-privacidad'),
            'coverageUrl' => route('public.cobertura.verificar'),
        ]);
    }

    public function store(Request $raw, LeadIntakeService $intake): RedirectResponse
    {
        // Honeypot: un campo invisible que solo llenan los bots. Se responde
        // como si todo saliera bien, sin crear nada.
        if (filled($raw->input('website'))) {
            return redirect()->route('public.solicitar')->with('success', 'Gracias, recibimos tu solicitud.');
        }

        /** @var SubmitLeadRequest $request */
        $request = app(SubmitLeadRequest::class);

        /** @var list<UploadedFile> $photos */
        $photos = array_values(array_filter((array) $request->file('photos', []), fn ($f) => $f instanceof UploadedFile));

        $lead = $intake->submit(
            $request->safe()->except(['photos', 'privacy', 'submission_key']),
            Attribution::resolve($request, $request->only([...Attribution::UTM_KEYS, 'referrer', 'landing_url'])),
            $photos,
            (string) $request->validated('submission_key'),
        );

        return redirect()->to(CrmLinks::thankYou($lead));
    }

    public function thanks(Lead $lead, CoverageService $coverage): Response
    {
        $lead->loadMissing('customer');
        $consultation = ConsultationSettings::current();
        $acceptsVisits = $coverage->acceptsVisits($lead->postal_code);

        return Inertia::render('public/solicitar/Gracias', [
            'seo' => app(Seo::class)->private('Recibimos tu solicitud'),
            'lead' => [
                'folio' => $lead->folio,
                'firstName' => $lead->customer->first_name,
                'email' => $lead->customer->email,
                'preferredContact' => $lead->customer->preferred_contact,
                'postalCode' => $lead->postal_code,
                'categories' => array_column($lead->categorySummaries(), 'name'),
            ],
            'consultation' => [
                'enabled' => $consultation->enabled,
                'isFree' => $consultation->isFree(),
                'price' => $consultation->price->amount,
                'durationMinutes' => $consultation->durationMinutes,
                'url' => $consultation->enabled ? CrmLinks::bookConsultation($lead) : null,
            ],
            'visit' => [
                'covered' => $lead->coverage_zone_id !== null,
                'acceptsVisits' => $acceptsVisits,
                'url' => $acceptsVisits ? CrmLinks::bookVisit($lead) : null,
            ],
        ]);
    }

    /**
     * Categorías publicadas del catálogo (o todas si aún no hay publicadas).
     * Se leen por tabla para no acoplarse al modelo del módulo web.
     *
     * @return list<array{id: int, name: string, slug: string, tagline: string|null, coverUrl: string|null}>
     */
    public static function categories(): array
    {
        $base = DB::table('product_categories')->whereNull('deleted_at');
        $hasPublished = (clone $base)->where('is_published', true)->exists();

        $rows = (clone $base)
            ->when($hasPublished, fn ($q) => $q->where('is_published', true))
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'tagline', 'cover_media_id']);

        $covers = Media::query()->whereIn('id', $rows->pluck('cover_media_id')->filter())->get()->keyBy('id');

        return array_values($rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'slug' => (string) $row->slug,
            'tagline' => $row->tagline !== null ? (string) $row->tagline : null,
            'coverUrl' => $row->cover_media_id && $covers->has($row->cover_media_id) ? $covers->get($row->cover_media_id)?->url() : null,
        ])->all());
    }
}
