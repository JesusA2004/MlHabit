<?php

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Services\Projects\QuoteCalculator;
use App\Services\Projects\QuoteService;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

function quotePayload(array $items, array $extra = []): array
{
    return ['title' => 'Cocina integral', 'items' => $items, ...$extra];
}

it('calcula totales con IVA incluido de forma exacta', function () {
    $totals = app(QuoteCalculator::class)->calculate([
        ['quantity' => '1', 'unit_price' => '100000.00'],
        ['quantity' => '2.5', 'unit_price' => '5400.00'],
    ], true, '0.1600');

    // 100000 + 13500 = 113500; base = 113500 / 1.16 = 97844.83; IVA = 15655.17
    expect($totals['subtotal'])->toBe('113500.00')
        ->and($totals['discount_total'])->toBe('0.00')
        ->and($totals['total'])->toBe('113500.00')
        ->and($totals['tax_total'])->toBe('15655.17');
});

it('calcula totales con IVA desglosado aparte', function () {
    $totals = app(QuoteCalculator::class)->calculate([
        ['quantity' => '3', 'unit_price' => '0.10'],
        ['quantity' => '1.333', 'unit_price' => '999.99'],
    ], false, '0.1600');

    // 0.30 + round(1332.98667) = 0.30 + 1332.99 = 1333.29; IVA = 213.3264 → 213.33
    expect($totals['items'][1]['gross'])->toBe('1332.99')
        ->and($totals['subtotal'])->toBe('1333.29')
        ->and($totals['tax_total'])->toBe('213.33')
        ->and($totals['total'])->toBe('1546.62');
});

it('aplica descuentos por partida y redondea medio hacia arriba', function () {
    $totals = app(QuoteCalculator::class)->calculate([
        ['quantity' => '1', 'unit_price' => '0.125', 'discount_amount' => '0'],
        ['quantity' => '1', 'unit_price' => '1000.00', 'discount_amount' => '100.00'],
    ], false, '0.1600');

    expect($totals['items'][0]['line_total'])->toBe('0.13')
        ->and($totals['discount_total'])->toBe('100.00')
        ->and($totals['subtotal'])->toBe('1000.13')
        ->and($totals['tax_total'])->toBe('144.02')
        ->and($totals['total'])->toBe('1044.15');
});

it('rechaza un descuento mayor que la partida', function () {
    app(QuoteCalculator::class)->calculate([['quantity' => '1', 'unit_price' => '10', 'discount_amount' => '11']], true, '0.16');
})->throws(ValidationException::class);

it('el backend recalcula e ignora totales enviados por el navegador', function () {
    $user = Fixtures::staff();
    $project = Fixtures::project();

    $this->actingAs($user)->post(route('admin.cotizaciones.store', $project), quotePayload(
        [['description' => 'Módulo alto', 'quantity' => '2', 'unit' => 'pza', 'unit_price' => '1,250.50']],
        ['total' => '1.00', 'subtotal' => '1.00'],
    ))->assertRedirect();

    $quote = Quote::query()->where('project_id', $project->id)->firstOrFail();
    expect($quote->total)->toBe('2501.00')
        ->and($quote->tax_rate)->toBe('0.1600')
        ->and($quote->prices_include_tax)->toBeTrue()
        ->and($quote->valid_until->toDateString())->toBe(now()->addDays(15)->toDateString());
});

it('congela IVA y modo de precios desde la configuración', function () {
    Settings::put('commercial', ['tax_rate' => '0.0800', 'prices_include_tax' => false]);
    $user = Fixtures::staff();
    $project = Fixtures::project();

    $quote = app(QuoteService::class)->create($project, quotePayload([
        ['description' => 'Clóset', 'quantity' => '1', 'unit' => 'pza', 'unit_price' => '1000.00'],
    ]), $user);

    expect($quote->tax_rate)->toBe('0.0800')->and($quote->total)->toBe('1080.00');

    Settings::put('commercial', ['tax_rate' => '0.1600']);
    expect($quote->refresh()->total)->toBe('1080.00');
});

it('un descuento sin permiso se rechaza', function () {
    $designer = Fixtures::staff(UserRole::Design);
    $project = Fixtures::project();

    $this->actingAs($designer)->post(route('admin.cotizaciones.store', $project), quotePayload([
        ['description' => 'Cocina', 'quantity' => '1', 'unit' => 'pza', 'unit_price' => '50000', 'discount_amount' => '5000'],
    ]))->assertSessionHasErrors('items');

    expect(Quote::query()->count())->toBe(0);
});

it('un descuento con permiso registra quién lo autorizó', function () {
    $boss = Fixtures::staff(UserRole::Direction);
    $project = Fixtures::project();

    $this->actingAs($boss)->post(route('admin.cotizaciones.store', $project), quotePayload([
        ['description' => 'Cocina', 'quantity' => '1', 'unit' => 'pza', 'unit_price' => '50000', 'discount_amount' => '5000'],
    ]))->assertRedirect();

    $quote = Quote::query()->firstOrFail();
    expect($quote->discount_total)->toBe('5000.00')
        ->and($quote->total)->toBe('45000.00')
        ->and($quote->discount_authorized_by)->toBe($boss->id);
});

it('editar una cotización enviada crea una nueva versión y reemplaza la anterior', function () {
    $user = Fixtures::staff();
    $project = Fixtures::project();
    $service = app(QuoteService::class);

    $v1 = $service->create($project, quotePayload([
        ['description' => 'Cocina', 'quantity' => '1', 'unit' => 'pza', 'unit_price' => '100000'],
    ]), $user);
    $service->send($v1, $user);

    $this->actingAs($user)->put(route('admin.cotizaciones.update', $v1), quotePayload([
        ['description' => 'Cocina', 'quantity' => '1', 'unit' => 'pza', 'unit_price' => '95000'],
    ]))->assertRedirect();

    $v1->refresh();
    $v2 = Quote::query()->where('project_id', $project->id)->where('version', 2)->firstOrFail();

    expect($v1->status)->toBe(QuoteStatus::Superseded)
        ->and($v1->total)->toBe('100000.00')
        ->and($v2->status)->toBe(QuoteStatus::Draft)
        ->and($v2->total)->toBe('95000.00')
        ->and($v2->folio)->not->toBe($v1->folio);
});

it('enviar lleva el proyecto de diseño a cotización', function () {
    $user = Fixtures::staff();
    $project = Fixtures::project();
    $quote = app(QuoteService::class)->create($project, quotePayload([
        ['description' => 'Cocina', 'quantity' => '1', 'unit' => 'pza', 'unit_price' => '1000'],
    ]), $user);

    $this->actingAs($user)->post(route('admin.cotizaciones.enviar', $quote))->assertSessionHas('success');

    expect($quote->refresh()->status)->toBe(QuoteStatus::Sent)
        ->and($project->refresh()->status->value)->toBe('quoting');
});

it('quotes:expire marca vencidas las enviadas fuera de vigencia', function () {
    $project = Fixtures::project();
    $old = Fixtures::sentQuote($project, '1000.00', ['valid_until' => now()->subDay()->toDateString()]);
    $current = Fixtures::sentQuote($project, '1000.00', ['valid_until' => now()->toDateString()]);

    $this->artisan('quotes:expire')->assertSuccessful();

    expect($old->refresh()->status)->toBe(QuoteStatus::Expired)
        ->and($current->refresh()->status)->toBe(QuoteStatus::Sent);
});

it('la vista imprimible es para el equipo y el cliente dueño', function () {
    [$owner, $customer] = Fixtures::client();
    [$other] = Fixtures::client();
    $quote = Fixtures::sentQuote(Fixtures::project($customer));

    $this->actingAs($owner)->get(route('cotizaciones.imprimir', $quote))->assertOk();
    $this->actingAs($other)->get(route('cotizaciones.imprimir', $quote))->assertForbidden();
    $this->actingAs(Fixtures::staff(UserRole::Sales))->get(route('cotizaciones.imprimir', $quote))->assertOk();
});
