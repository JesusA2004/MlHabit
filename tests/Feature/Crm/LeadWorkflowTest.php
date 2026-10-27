<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\Crm\LeadAssigner;
use App\Services\Crm\LeadWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->workflow = app(LeadWorkflow::class);
    $this->user = User::factory()->role(UserRole::Sales)->create()->refresh();
});

it('moves a lead through allowed transitions and logs activity and audit', function () {
    $lead = Lead::factory()->create();

    $this->workflow->transition($lead, LeadStatus::Contacted, $this->user, 'Primera llamada');

    expect($lead->refresh()->status)->toBe(LeadStatus::Contacted)
        ->and($lead->first_contacted_at)->not->toBeNull();

    $activity = $lead->activities()->where('type', LeadActivityType::StatusChanged->value)->sole();
    expect($activity->from_status)->toBe(LeadStatus::New)
        ->and($activity->to_status)->toBe(LeadStatus::Contacted)
        ->and($activity->body)->toBe('Primera llamada')
        ->and($activity->user_id)->toBe($this->user->id);

    expect(ActivityLog::query()->where('action', 'prospecto.estado')->where('subject_id', $lead->id)->exists())->toBeTrue();
});

it('is idempotent when asking for the current status', function () {
    $lead = Lead::factory()->status(LeadStatus::Contacted)->create();

    $this->workflow->transition($lead, LeadStatus::Contacted, $this->user);

    expect($lead->activities()->count())->toBe(0);
});

it('rejects transitions outside the matrix', function () {
    $lead = Lead::factory()->status(LeadStatus::Won)->create();

    $this->workflow->transition($lead, LeadStatus::Contacted, $this->user);
})->throws(ValidationException::class);

it('requires a lost reason', function () {
    $lead = Lead::factory()->create();

    expect(fn () => $this->workflow->transition($lead, LeadStatus::Lost, $this->user))->toThrow(ValidationException::class);

    $this->workflow->transition($lead, LeadStatus::Lost, $this->user, attributes: ['lost_reason' => 'price']);
    expect($lead->refresh()->lost_reason)->toBe(LostReason::Price)->and($lead->closed_at)->not->toBeNull();
});

it('lets the system force forward transitions but never backwards', function () {
    $lead = Lead::factory()->status(LeadStatus::Contacted)->create();

    // Visitas: pago confirmado.
    $this->workflow->transition($lead, LeadStatus::VisitPaid, null, force: true);
    expect($lead->refresh()->status)->toBe(LeadStatus::VisitPaid);

    // Llamada tardía hacia atrás: no hace nada.
    $this->workflow->transition($lead, LeadStatus::ConsultationScheduled, null, force: true);
    expect($lead->refresh()->status)->toBe(LeadStatus::VisitPaid);

    // Repetida: idempotente.
    $this->workflow->transition($lead, LeadStatus::VisitPaid, null, force: true);
    expect($lead->activities()->where('type', 'status_changed')->count())->toBe(1);
});

it('does not reopen closed leads with forced transitions', function () {
    $lead = Lead::factory()->status(LeadStatus::Cancelled)->create();

    $this->workflow->transition($lead, LeadStatus::VisitDone, null, force: true);

    expect($lead->refresh()->status)->toBe(LeadStatus::Cancelled);
});

it('changes status from the admin panel with validation', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->user)
        ->patch(route('admin.prospectos.etapa', $lead), ['status' => 'lost'])
        ->assertSessionHasErrors('lost_reason');

    $this->actingAs($this->user)
        ->patch(route('admin.prospectos.etapa', $lead), ['status' => 'contacted', 'note' => 'Llamé'])
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->status)->toBe(LeadStatus::Contacted);
});

it('records manual interactions and marks first contact', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->user)
        ->post(route('admin.prospectos.bitacora', $lead), ['type' => 'call', 'body' => 'Le interesa cocina en L'])
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->first_contacted_at)->not->toBeNull()
        ->and($lead->activities()->where('type', 'call')->exists())->toBeTrue();
});

it('assigns leads only with leads.assign', function () {
    $lead = Lead::factory()->create();
    $other = User::factory()->role(UserRole::Sales)->create()->refresh();
    $director = User::factory()->role(UserRole::Direction)->create()->refresh();

    $this->actingAs($this->user)->patch(route('admin.prospectos.responsable', $lead), ['assigned_to' => $other->id])->assertForbidden();

    $this->actingAs($director)->patch(route('admin.prospectos.responsable', $lead), ['assigned_to' => $other->id])->assertSessionHasNoErrors();
    expect($lead->refresh()->assigned_to)->toBe($other->id)
        ->and($lead->activities()->where('type', 'assigned')->exists())->toBeTrue();
});

it('round-robins new leads among active sales users', function () {
    $a = User::factory()->role(UserRole::Sales)->create()->refresh();
    $b = User::factory()->role(UserRole::Sales)->create()->refresh();
    User::factory()->role(UserRole::Sales)->inactive()->create();
    $this->user->update(['is_active' => false]);

    $assigner = app(LeadAssigner::class);
    $first = $assigner->next();
    Lead::factory()->create(['assigned_to' => $first->id]);
    $second = $assigner->next();
    Lead::factory()->create(['assigned_to' => $second->id]);
    $third = $assigner->next();

    expect([$first->id, $second->id])->toEqualCanonicalizing([$a->id, $b->id])
        ->and($third->id)->toBe($first->id);
});
