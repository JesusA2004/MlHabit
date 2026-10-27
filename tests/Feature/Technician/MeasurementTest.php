<?php

use App\Enums\MeasurementStatus;
use App\Models\Customer;
use App\Models\Measurement;
use App\Services\Projects\MeasurementService;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

function measurementPayload(int $width = 3200): array
{
    return [
        'space_type' => 'Cocina',
        'access_notes' => 'Elevador de servicio',
        'items' => [
            ['area' => 'Muro A', 'kind' => 'width', 'value_mm' => $width],
            ['area' => 'Muro A', 'kind' => 'height', 'value_mm' => 2400],
            ['area' => 'Muro A', 'kind' => 'outlet', 'label' => 'Contacto a 1100 mm', 'value_mm' => 1100],
        ],
    ];
}

it('el técnico ve hoy y sus visitas', function () {
    $tech = Fixtures::technician();
    Fixtures::visit(Customer::factory()->create(), ['technician_id' => $tech->id]);

    $this->actingAs($tech)->get(route('tecnico.hoy'))->assertOk();
    $this->actingAs($tech)->get(route('tecnico.visitas.index'))->assertOk();
});

it('un técnico no puede ver ni medir la visita de otro (IDOR)', function () {
    $mine = Fixtures::technician();
    $other = Fixtures::technician();
    $visit = Fixtures::visit(Customer::factory()->create(), ['technician_id' => $other->id, 'status' => 'in_progress']);
    $measurement = Measurement::factory()->create(['project_id' => null, 'site_visit_id' => $visit->id, 'technician_id' => $other->id]);

    $this->actingAs($mine)->get(route('tecnico.visitas.show', $visit))->assertForbidden();
    $this->actingAs($mine)->post(route('tecnico.visitas.medicion', $visit))->assertForbidden();
    $this->actingAs($mine)->get(route('tecnico.mediciones.edit', $measurement))->assertForbidden();
    $this->actingAs($mine)->put(route('tecnico.mediciones.update', $measurement), measurementPayload())->assertForbidden();
});

it('un cliente o el personal no entran a la app de técnicos', function () {
    [$client] = Fixtures::client();

    $this->actingAs($client)->get(route('tecnico.hoy'))->assertForbidden();
});

it('registra, guarda y envía la medición en milímetros', function () {
    $tech = Fixtures::technician();
    $visit = Fixtures::visit(Customer::factory()->create(), ['technician_id' => $tech->id, 'status' => 'in_progress']);

    $this->actingAs($tech)->post(route('tecnico.visitas.medicion', $visit))->assertRedirect();
    $measurement = Measurement::query()->where('site_visit_id', $visit->id)->firstOrFail();

    $this->actingAs($tech)->put(route('tecnico.mediciones.update', $measurement), measurementPayload())->assertSessionHas('success');
    $this->actingAs($tech)->post(route('tecnico.mediciones.enviar', $measurement))->assertRedirect(route('tecnico.visitas.show', $visit));

    $measurement->refresh();
    expect($measurement->status)->toBe(MeasurementStatus::Submitted)
        ->and($measurement->items()->count())->toBe(3)
        ->and($measurement->items()->where('kind', 'width')->value('value_mm'))->toBe(3200);
});

it('rechaza medidas con decimales', function () {
    $tech = Fixtures::technician();
    $visit = Fixtures::visit(Customer::factory()->create(), ['technician_id' => $tech->id, 'status' => 'in_progress']);
    $measurement = app(MeasurementService::class)->draftForVisit($visit, $tech);

    $payload = measurementPayload();
    $payload['items'][0]['value_mm'] = '3200.5';
    $this->actingAs($tech)->put(route('tecnico.mediciones.update', $measurement), $payload)->assertSessionHasErrors('items.0.value_mm');
});

it('una medición enviada no se sobrescribe: la corrección crea nueva versión', function () {
    $tech = Fixtures::technician();
    $project = Fixtures::project();
    $visit = Fixtures::visit($project->customer, ['technician_id' => $tech->id, 'status' => 'in_progress', 'project_id' => $project->id]);
    $service = app(MeasurementService::class);

    $v1 = $service->draftForVisit($visit, $tech);
    $service->saveDraft($v1, measurementPayload(3200));
    $service->submit($v1, $tech);

    $this->actingAs($tech)->put(route('tecnico.mediciones.update', $v1), measurementPayload(9999))->assertSessionHasErrors('measurement');

    $this->actingAs($tech)->post(route('tecnico.mediciones.corregir', $v1))->assertRedirect();
    $v2 = Measurement::query()->where('supersedes_id', $v1->id)->firstOrFail();
    $this->actingAs($tech)->put(route('tecnico.mediciones.update', $v2), measurementPayload(3150))->assertSessionHas('success');
    $this->actingAs($tech)->post(route('tecnico.mediciones.enviar', $v2))->assertRedirect();

    $v1->refresh();
    $v2->refresh();
    expect($v1->status)->toBe(MeasurementStatus::Superseded)
        ->and($v1->items()->where('kind', 'width')->value('value_mm'))->toBe(3200)
        ->and($v2->version)->toBe(2)
        ->and($v2->project_id)->toBe($project->id)
        ->and($v2->status)->toBe(MeasurementStatus::Submitted)
        ->and($v2->items()->where('kind', 'width')->value('value_mm'))->toBe(3150);
});

it('no permite enviar una medición vacía', function () {
    $tech = Fixtures::technician();
    $visit = Fixtures::visit(Customer::factory()->create(), ['technician_id' => $tech->id, 'status' => 'in_progress']);
    $measurement = app(MeasurementService::class)->draftForVisit($visit, $tech);

    $this->actingAs($tech)->post(route('tecnico.mediciones.enviar', $measurement))->assertSessionHasErrors('items');
});

it('no completa la visita sin medición enviada', function () {
    $tech = Fixtures::technician();
    $visit = Fixtures::visit(Customer::factory()->create(), ['technician_id' => $tech->id, 'status' => 'in_progress']);

    $this->actingAs($tech)->post(route('tecnico.visitas.completar', $visit))->assertSessionHas('error');
    expect($visit->refresh()->status->value)->toBe('in_progress');
});
