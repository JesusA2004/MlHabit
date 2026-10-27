<?php

use App\Enums\DesignStatus;
use App\Enums\MediaVisibility;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Models\DesignVersion;
use App\Models\Media;
use App\Models\Quote;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

it('muestra las secciones del portal al cliente', function () {
    [$client, $customer] = Fixtures::client();
    $project = Fixtures::project($customer);
    Fixtures::sentQuote($project);

    foreach (['portal.inicio', 'portal.citas', 'portal.documentos', 'portal.garantias', 'portal.ayuda', 'portal.cotizaciones.index', 'portal.disenos.index', 'portal.pagos.index'] as $name) {
        $this->actingAs($client)->get(route($name))->assertOk();
    }
});

it('el portal funciona aunque el cliente aún no tenga proyecto', function () {
    [$client] = Fixtures::client();

    $this->actingAs($client)->get(route('portal.inicio'))->assertOk();
});

it('el personal y los técnicos no entran al portal', function () {
    $this->actingAs(Fixtures::staff())->get(route('portal.inicio'))->assertForbidden();
    $this->actingAs(Fixtures::technician())->get(route('portal.inicio'))->assertForbidden();
});

it('un cliente no ve la cotización, el contrato ni los archivos de otro (IDOR)', function () {
    [$alice, $aliceCustomer] = Fixtures::client();
    [$bob] = Fixtures::client();
    $project = Fixtures::project($aliceCustomer, ProjectStatus::Quoting);
    $quote = Fixtures::sentQuote($project);
    $contract = Fixtures::contract($project);

    $this->actingAs($bob)->get(route('portal.cotizaciones.show', $quote))->assertNotFound();
    $this->actingAs($bob)->post(route('portal.cotizaciones.aceptar', $quote), ['confirm' => true])->assertNotFound();
    $this->actingAs($bob)->post(route('portal.cotizaciones.rechazar', $quote), ['reason' => 'no'])->assertNotFound();
    $this->actingAs($bob)->get(route('portal.pagos.anticipo', $contract))->assertNotFound();
    $this->actingAs($bob)->post(route('portal.proyectos.seleccionar', $project))->assertNotFound();
    $this->actingAs($bob)->get(route('cotizaciones.imprimir', $quote))->assertForbidden();

    expect(Gate::forUser($bob)->allows('view', $project))->toBeFalse()
        ->and(Gate::forUser($alice)->allows('view', $project))->toBeTrue()
        ->and(Gate::forUser($bob)->allows('view', $contract))->toBeFalse();

    $this->actingAs($alice)->get(route('portal.cotizaciones.show', $quote))->assertOk();
});

it('las listas del portal solo incluyen lo del cliente', function () {
    [$alice, $aliceCustomer] = Fixtures::client();
    [, $bobCustomer] = Fixtures::client();
    $mine = Fixtures::sentQuote(Fixtures::project($aliceCustomer), '1000.00', ['title' => 'Cocina Alicia']);
    Fixtures::sentQuote(Fixtures::project($bobCustomer), '2000.00', ['title' => 'Clóset Bob']);
    Fixtures::payment($bobCustomer, PaymentConcept::VisitFee, '1500.00');

    $this->actingAs($alice)->get(route('portal.cotizaciones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Cotizaciones')
            ->has('quotes', 1)
            ->where('quotes.0.uuid', $mine->uuid));

    $this->actingAs($alice)->get(route('portal.pagos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('payments', 0));
});

it('los borradores no son visibles al cliente', function () {
    [$client, $customer] = Fixtures::client();
    $project = Fixtures::project($customer);
    $draft = Quote::factory()->create(['project_id' => $project->id, 'status' => QuoteStatus::Draft]);
    $design = DesignVersion::factory()->create(['project_id' => $project->id, 'status' => DesignStatus::Draft]);

    $this->actingAs($client)->get(route('portal.cotizaciones.show', $draft))->assertNotFound();
    expect(Gate::forUser($client)->allows('view', $design))->toBeFalse();
});

it('los documentos del portal solo muestran archivos marcados para el cliente', function () {
    [$client, $customer] = Fixtures::client();
    $project = Fixtures::project($customer);
    $internal = Media::factory()->create(['visibility' => MediaVisibility::Private, 'original_filename' => 'costos-internos.pdf', 'title' => null]);
    $shared = Media::factory()->create(['visibility' => MediaVisibility::Private, 'original_filename' => 'garantia-firmada.pdf', 'title' => null]);
    $project->attachMedia($internal->id, 'document');
    $project->attachMedia($shared->id, 'client_document');

    $this->actingAs($client)->get(route('portal.documentos'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Documentos')
            ->has('groups.0.files', 1)
            ->where('groups.0.files.0.name', 'garantia-firmada.pdf'));
});

it('el cobro del anticipo rechaza pagos de otro cliente', function () {
    [, $aliceCustomer] = Fixtures::client();
    [$bob] = Fixtures::client();
    $project = Fixtures::project($aliceCustomer, ProjectStatus::PendingDeposit);
    $contract = Fixtures::contract($project);
    $payment = Fixtures::payment($aliceCustomer, PaymentConcept::Deposit, '11350.00', PaymentStatus::Pending, ['contract_id' => $contract->id, 'project_id' => $project->id]);

    $this->actingAs($bob)->postJson(route('portal.pagos.cobrar', $payment->uuid), ['token_id' => 'tok', 'device_session_id' => 'dev'])->assertNotFound();
});
