<?php

use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\InstallationOrderItem;
use App\Models\Supplier;
use App\Notifications\Operations\InstallationScheduled;
use App\Services\Operations\InstallationService;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->ops = OpsSetup::staff(UserRole::Operations);
});

it('computes totals server-side: subtotal + mobilization', function () {
    // 2 × 850.00 + 1.5 × 1200.50 = 1700.00 + 1800.75 = 3500.75 ; + 500 movilización
    $order = OpsSetup::order(card: OpsSetup::activeRateCard('500.00', '0.00'));

    expect($order->subtotal)->toBe('3500.75')
        ->and($order->total)->toBe('4000.75')
        ->and($order->folio)->toStartWith('MLH-I-');
});

it('applies the minimum charge when subtotal plus mobilization is lower', function () {
    $order = OpsSetup::order(card: OpsSetup::activeRateCard('100.00', '5000.00'), quantities: ['ALTO-60' => '1']);

    expect($order->subtotal)->toBe('850.00')
        ->and($order->total)->toBe('5000.00');
});

it('rounds decimal quantities half-up per line', function () {
    // 0.333 × 1200.50 = 399.7665 → 399.77
    $order = OpsSetup::order(card: OpsSetup::activeRateCard('0', '0'), quantities: ['BAJO-80' => '0.333']);

    expect($order->items()->value('line_total'))->toBe('399.77')
        ->and($order->total)->toBe('399.77');
});

it('counts extras only after approval by someone other than the lead installer', function () {
    $lead = OpsSetup::installer();
    $order = OpsSetup::order(card: OpsSetup::activeRateCard('0', '0'), lead: $lead, quantities: ['ALTO-60' => '1']);

    $this->actingAs($this->ops)->post(route('admin.instalaciones.extras.store', $order), [
        'description' => 'Recorte de zoclo',
        'unit' => 'ml',
        'quantity' => '2',
        'unit_price' => '150.00',
    ])->assertSessionHasNoErrors();

    $extra = InstallationOrderItem::query()->where('is_extra', true)->sole();
    expect($order->refresh()->total)->toBe('850.00');

    $this->actingAs($this->ops)->post(route('admin.instalaciones.extras.aprobar', [$order, $extra]))->assertSessionHasNoErrors();

    expect($extra->refresh()->extra_approved_by)->toBe($this->ops->id)
        ->and($order->refresh()->subtotal)->toBe('1150.00')
        ->and($order->total)->toBe('1150.00');
});

it('rejects extra approval by the lead installer even if they had installations.manage', function () {
    $lead = OpsSetup::installer();
    $order = OpsSetup::order(lead: $lead);
    $extra = app(InstallationService::class)->addExtra($order, [
        'description' => 'Extra', 'unit' => 'pieza', 'quantity' => '1', 'unit_price' => '300.00',
    ], $lead);

    // El técnico no entra a /admin.
    $this->actingAs($lead)->post(route('admin.instalaciones.extras.aprobar', [$order, $extra]))->assertForbidden();

    // Aun con un rol con installations.manage, si es el líder de la orden no puede aprobar.
    $lead->update(['role' => UserRole::Operations]);
    $this->actingAs($lead->refresh())->post(route('admin.instalaciones.extras.aprobar', [$order, $extra]))->assertForbidden();

    expect($extra->refresh()->extra_approved_at)->toBeNull();
});

it('lets the lead installer propose extras from the field app, pending approval', function () {
    $lead = OpsSetup::installer();
    $order = OpsSetup::forceStatus(OpsSetup::order(lead: $lead), InstallationStatus::InProgress, ProjectStatus::Installing);
    $total = $order->total;

    $this->actingAs($lead)->post(route('tecnico.instalaciones.extras.store', $order), [
        'rate_card_item_id' => $order->rateCard->items()->where('code', 'ALTO-60')->value('id'),
        'description' => 'Alacena adicional',
        'unit' => 'pieza',
        'quantity' => '1',
    ])->assertSessionHasNoErrors();

    $extra = InstallationOrderItem::query()->where('is_extra', true)->sole();
    expect($extra->unit_price)->toBe('850.00')
        ->and($extra->isPendingExtra())->toBeTrue()
        ->and($order->refresh()->total)->toBe($total);
});

it('schedules an installation, moves the project and notifies the client', function () {
    $client = OperationsFixtures::client();
    $project = OperationsFixtures::project(ProjectStatus::ReadyToInstall, $client);
    $order = OpsSetup::order(project: $project);

    $this->actingAs($this->ops)->post(route('admin.instalaciones.programar', $order), [
        'date' => now()->addDays(3)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '17:00',
    ])->assertSessionHasNoErrors();

    expect($order->refresh()->status)->toBe(InstallationStatus::Scheduled)
        ->and($order->scheduled_start)->not->toBeNull()
        ->and($project->refresh()->status)->toBe(ProjectStatus::InstallationScheduled);

    Notification::assertSentTo($client, InstallationScheduled::class);
});

it('does not schedule while fabrication is unfinished', function () {
    $project = OperationsFixtures::project(ProjectStatus::Fabrication);
    $order = OpsSetup::order(project: $project);

    $this->actingAs($this->ops)->post(route('admin.instalaciones.programar', $order), [
        'date' => now()->addDays(3)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '17:00',
    ])->assertSessionHasErrors('scheduled_start');
});

it('locks items once the installation started', function () {
    $order = OpsSetup::forceStatus(OpsSetup::order(), InstallationStatus::InProgress);
    $item = $order->items()->first();

    $this->actingAs($this->ops)->put(route('admin.instalaciones.partidas.update', [$order, $item]), ['quantity' => '10'])
        ->assertSessionHasErrors('items');
    $this->actingAs($this->ops)->delete(route('admin.instalaciones.partidas.destroy', [$order, $item]))
        ->assertSessionHasErrors('items');
});

it('requires a technician who does installations as lead installer', function () {
    $visitsOnly = OpsSetup::installer();
    $visitsOnly->technicianProfile->update(['does_installations' => false]);

    $card = OpsSetup::activeRateCard();
    $project = OperationsFixtures::project(ProjectStatus::ReadyToInstall);

    $this->actingAs($this->ops)->post(route('admin.instalaciones.store'), [
        'project_id' => $project->id,
        'supplier_id' => Supplier::factory()->installer()->create()->id,
        'rate_card_id' => $card->id,
        'lead_installer_id' => $visitsOnly->id,
        'items' => [['rate_card_item_id' => $card->items()->value('id'), 'quantity' => '1']],
    ])->assertSessionHasErrors('lead_installer_id');
});
