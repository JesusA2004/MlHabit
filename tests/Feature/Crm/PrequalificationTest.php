<?php

use App\Enums\LeadStatus;
use App\Enums\PrequalificationStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Prequalification;
use App\Models\User;
use App\Notifications\Crm\PrequalificationRequested;
use App\Notifications\Crm\PrequalificationReviewed;
use App\Notifications\Crm\PrequalificationSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Bus::fake();
    Storage::fake('media_private');
    $this->credit = User::factory()->role(UserRole::Credit)->create()->refresh();
    $this->sales = User::factory()->role(UserRole::Sales)->create()->refresh();
});

it('runs the manual review workflow end to end', function () {
    $lead = Lead::factory()->status(LeadStatus::Contacted)->create();

    // Comercial inicia y se envía el enlace al cliente.
    $this->actingAs($this->sales)->post(route('admin.prospectos.precalificacion.store', $lead), ['send_link' => true])->assertRedirect();
    $p = Prequalification::query()->sole();
    expect($p->status)->toBe(PrequalificationStatus::Draft)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Prequalification);
    Notification::assertSentOnDemand(PrequalificationRequested::class);

    // Cliente envía con documento.
    $this->post(URL::signedRoute('public.precalificacion.store', ['lead' => $lead->uuid]), [
        'requested_amount' => '85,000',
        'preferred_term_months' => 12,
        'income_range' => '30k_60k',
        'employment_type' => 'employee',
        'documents' => [UploadedFile::fake()->createWithContent('ine.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")],
        'consent' => '1',
    ])->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->status)->toBe(PrequalificationStatus::Submitted)
        ->and($p->requested_amount)->toBe('85000.00')
        ->and($p->mediaWithRole('document')->count())->toBe(1);
    Notification::assertSentTo($this->credit, PrequalificationSubmitted::class);

    // Revisión → información solicitada (requiere nota) → reenvío → decisión.
    $this->actingAs($this->credit)->post(route('admin.precalificaciones.estado', $p), ['status' => 'in_review'])->assertSessionHasNoErrors();
    $this->actingAs($this->credit)->post(route('admin.precalificaciones.estado', $p), ['status' => 'info_requested'])->assertSessionHasErrors('notes');
    $this->actingAs($this->credit)->post(route('admin.precalificaciones.estado', $p), ['status' => 'info_requested', 'notes' => 'Falta comprobante de domicilio'])->assertSessionHasNoErrors();

    $this->post(URL::signedRoute('public.precalificacion.store', ['lead' => $lead->uuid]), [
        'income_range' => '30k_60k',
        'employment_type' => 'employee',
        'documents' => [UploadedFile::fake()->image('domicilio.jpg')],
        'consent' => '1',
    ])->assertSessionHasNoErrors();
    expect($p->refresh()->status)->toBe(PrequalificationStatus::Submitted);

    $this->actingAs($this->credit)->post(route('admin.precalificaciones.estado', $p), ['status' => 'in_review']);
    $this->actingAs($this->credit)->post(route('admin.precalificaciones.estado', $p), ['status' => 'prequalified', 'notes' => 'Ingresos suficientes para 12 meses'])->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->status)->toBe(PrequalificationStatus::Prequalified)
        ->and($p->reviewer_id)->toBe($this->credit->id)
        ->and($p->reviewed_at)->not->toBeNull()
        ->and($p->status->label())->toContain('sujeto a evaluación');
    Notification::assertSentOnDemand(PrequalificationReviewed::class);

    expect(ActivityLog::query()->where('subject_type', Prequalification::class)->where('subject_id', $p->id)->count())->toBeGreaterThanOrEqual(6);
});

it('does not accept customer input while under review', function () {
    $p = Prequalification::factory()->status(PrequalificationStatus::InReview)->create();

    $this->post(URL::signedRoute('public.precalificacion.store', ['lead' => $p->lead->uuid]), [
        'income_range' => '30k_60k', 'employment_type' => 'employee', 'consent' => '1',
    ])->assertSessionHasErrors('form');
});

it('rejects invalid document types', function () {
    $lead = Lead::factory()->create();

    $this->post(URL::signedRoute('public.precalificacion.store', ['lead' => $lead->uuid]), [
        'income_range' => '30k_60k', 'employment_type' => 'employee', 'consent' => '1',
        'documents' => [UploadedFile::fake()->createWithContent('nomina.pdf', '<html><body>x</body></html>')],
    ])->assertSessionHasErrors('documents.0');
});

it('restricts the review screens and actions by permission', function () {
    $p = Prequalification::factory()->status(PrequalificationStatus::Submitted)->create();
    $finance = User::factory()->role(UserRole::Finance)->create()->refresh();

    // Sin prequalification.view.
    $this->actingAs($finance)->get(route('admin.precalificaciones.show', $p))->assertForbidden();
    $this->actingAs($finance)->get(route('admin.precalificaciones.index'))->assertForbidden();

    // Comercial ve el resumen pero no los documentos ni puede dictaminar.
    $this->actingAs($this->sales)->get(route('admin.precalificaciones.show', $p))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/precalificaciones/Show')->where('documents', null)->where('can.review', false));
    $this->actingAs($this->sales)->post(route('admin.precalificaciones.estado', $p), ['status' => 'in_review'])->assertForbidden();

    // Crédito (con sensitive.view) ve documentos.
    $this->actingAs($this->credit)->get(route('admin.precalificaciones.show', $p))
        ->assertInertia(fn ($page) => $page->where('can.viewDocuments', true)->has('documents'));

    // La policy `view` (la usa archivos.show) exige sensitive.view.
    expect($this->sales->can('view', $p))->toBeFalse()
        ->and($this->credit->can('view', $p))->toBeTrue();
});

it('never decides automatically and blocks invalid jumps', function () {
    $p = Prequalification::factory()->status(PrequalificationStatus::Submitted)->create();

    $this->actingAs($this->credit)->post(route('admin.precalificaciones.estado', $p), ['status' => 'prequalified', 'notes' => 'ok'])
        ->assertSessionHasErrors('status');
    expect($p->refresh()->status)->toBe(PrequalificationStatus::Submitted);
});
