<?php

use App\Enums\ProjectStatus;
use App\Enums\RateCardStatus;
use App\Enums\UserRole;
use App\Models\InstallationOrderItem;
use App\Models\RateCard;
use App\Models\Supplier;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->ops = OpsSetup::staff(UserRole::Operations);
});

function draftPayload(array $overrides = []): array
{
    return [
        'name' => 'Instalación CDMX',
        'mobilization_fee' => '450.00',
        'minimum_charge' => '2000.00',
        'items' => [
            ['code' => 'alto-60', 'description' => 'Alacena 60 cm', 'unit' => 'pieza', 'unit_price' => '800.00'],
            ['code' => 'BAJO-80', 'description' => 'Bajo 80 cm', 'unit' => 'pieza', 'unit_price' => '1100.00'],
        ],
        ...$overrides,
    ];
}

it('creates a draft, activates it and retires the previous active version', function () {
    $this->actingAs($this->ops)->post(route('admin.tarifarios.store'), draftPayload())->assertRedirect();
    $v1 = RateCard::query()->sole();

    expect($v1->version)->toBe(1)
        ->and($v1->status)->toBe(RateCardStatus::Draft)
        ->and($v1->items()->pluck('code')->all())->toBe(['ALTO-60', 'BAJO-80']);

    $this->actingAs($this->ops)->post(route('admin.tarifarios.activar', $v1))->assertSessionHasNoErrors();
    expect($v1->refresh()->status)->toBe(RateCardStatus::Active)->and($v1->activated_at)->not->toBeNull();

    $this->actingAs($this->ops)->post(route('admin.tarifarios.nueva-version', $v1))->assertRedirect();
    $v2 = RateCard::query()->where('version', 2)->sole();

    expect($v2->status)->toBe(RateCardStatus::Draft)
        ->and($v2->items()->count())->toBe(2)
        ->and($v2->name)->toBe('Instalación CDMX');

    $this->actingAs($this->ops)->put(route('admin.tarifarios.update', $v2), [
        ...collect(draftPayload())->except('name')->all(),
        'items' => [['code' => 'ALTO-60', 'description' => 'Alacena 60 cm', 'unit' => 'pieza', 'unit_price' => '900.00']],
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->ops)->post(route('admin.tarifarios.activar', $v2))->assertSessionHasNoErrors();

    expect($v1->refresh()->status)->toBe(RateCardStatus::Retired)
        ->and($v2->refresh()->status)->toBe(RateCardStatus::Active)
        ->and($v1->items()->where('code', 'ALTO-60')->value('unit_price'))->toBe('800.00');
});

it('never modifies active or retired versions', function () {
    $card = OpsSetup::activeRateCard();

    $this->actingAs($this->ops)->put(route('admin.tarifarios.update', $card), [
        ...collect(draftPayload())->except('name')->all(),
    ])->assertSessionHasErrors('status');

    expect($card->refresh()->mobilization_fee)->toBe('500.00')
        ->and($card->items()->where('code', 'ALTO-60')->value('unit_price'))->toBe('850.00');

    $this->actingAs($this->ops)->delete(route('admin.tarifarios.destroy', $card))->assertSessionHasErrors('status');
    expect(RateCard::query()->whereKey($card->id)->exists())->toBeTrue();
});

it('allows only one draft per rate card name', function () {
    $card = OpsSetup::activeRateCard();
    $this->actingAs($this->ops)->post(route('admin.tarifarios.nueva-version', $card))->assertRedirect();
    $this->actingAs($this->ops)->post(route('admin.tarifarios.nueva-version', $card))->assertSessionHasErrors('version');

    expect(RateCard::query()->where('name', $card->name)->count())->toBe(2);
});

it('requires items to activate and unique codes', function () {
    $this->actingAs($this->ops)->post(route('admin.tarifarios.store'), draftPayload(['items' => []]))->assertRedirect();
    $draft = RateCard::query()->sole();

    $this->actingAs($this->ops)->post(route('admin.tarifarios.activar', $draft))->assertSessionHasErrors('items');

    $this->actingAs($this->ops)->put(route('admin.tarifarios.update', $draft), [
        ...collect(draftPayload())->except('name')->all(),
        'items' => [
            ['code' => 'A', 'description' => 'x', 'unit' => 'pieza', 'unit_price' => '1'],
            ['code' => 'a', 'description' => 'y', 'unit' => 'pieza', 'unit_price' => '2'],
        ],
    ])->assertSessionHasErrors('items');
});

it('freezes prices on installation orders: later rate card versions never affect them', function () {
    $card = OpsSetup::activeRateCard(mobilization: '500.00');
    $order = OpsSetup::order(card: $card, quantities: ['ALTO-60' => '2']);

    expect($order->mobilization_fee)->toBe('500.00')
        ->and($order->total)->toBe('2200.00');

    // Nueva versión con precios distintos, activada.
    $this->actingAs($this->ops)->post(route('admin.tarifarios.nueva-version', $card));
    $v2 = RateCard::query()->where('name', $card->name)->where('version', 2)->sole();
    $this->actingAs($this->ops)->put(route('admin.tarifarios.update', $v2), [
        'mobilization_fee' => '999.00',
        'minimum_charge' => '0',
        'items' => [['code' => 'ALTO-60', 'description' => 'Alacena', 'unit' => 'pieza', 'unit_price' => '5000.00']],
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->ops)->post(route('admin.tarifarios.activar', $v2))->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->mobilization_fee)->toBe('500.00')
        ->and($order->total)->toBe('2200.00')
        ->and(InstallationOrderItem::query()->where('installation_order_id', $order->id)->value('unit_price'))->toBe('850.00');

    // Una orden nueva no puede usar el tarifario retirado.
    $project = OperationsFixtures::project(ProjectStatus::ReadyToInstall);
    $this->actingAs($this->ops)->post(route('admin.instalaciones.store'), [
        'project_id' => $project->id,
        'supplier_id' => Supplier::factory()->installer()->create()->id,
        'rate_card_id' => $card->id,
        'items' => [['rate_card_item_id' => $card->items()->value('id'), 'quantity' => '1']],
    ])->assertSessionHasErrors('rate_card_id');
});

it('forbids rate card management without permission', function () {
    $finance = OpsSetup::staff(UserRole::Finance);

    $this->actingAs($finance)->get(route('admin.tarifarios.index'))->assertOk();
    $this->actingAs($finance)->post(route('admin.tarifarios.store'), draftPayload())->assertForbidden();
});
