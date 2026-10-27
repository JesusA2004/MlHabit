<?php

use App\Enums\ContractStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Enums\VisitCreditStatus;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRefunded;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\ProjectEvent;
use App\Models\Refund;
use App\Models\VisitCredit;
use App\Services\Projects\ContractService;
use App\Support\Settings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

it('ejemplo 113500 / 10% / crédito 1500 → anticipo por pagar 9850', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    Fixtures::credit($customer, '1500.00', ['project_id' => $project->id]);
    $quote = Fixtures::sentQuote($project, '113500.00');

    $contract = app(ContractService::class)->acceptQuote($quote, $user, null, '127.0.0.1');

    expect($contract->contract_amount)->toBe('113500.00')
        ->and($contract->deposit_percent)->toBe('10.00')
        ->and($contract->deposit_amount)->toBe('11350.00')
        ->and($contract->visit_credit_amount)->toBe('1500.00')
        ->and($contract->deposit_due_amount)->toBe('9850.00')
        ->and($contract->folio)->toStartWith('MLH-K-')
        ->and($contract->status)->toBe(ContractStatus::PendingDeposit)
        ->and($project->refresh()->status)->toBe(ProjectStatus::PendingDeposit)
        ->and($quote->refresh()->status)->toBe(QuoteStatus::Accepted);
});

it('aceptar dos veces devuelve un solo contrato', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    $quote = Fixtures::sentQuote($project);

    $this->actingAs($user)->post(route('portal.cotizaciones.aceptar', $quote), ['confirm' => true])->assertRedirect();
    $this->actingAs($user)->post(route('portal.cotizaciones.aceptar', $quote), ['confirm' => true])->assertRedirect();
    $again = app(ContractService::class)->acceptQuote($quote->refresh(), $user);

    expect(Contract::query()->where('project_id', $project->id)->count())->toBe(1)
        ->and($again->id)->toBe(Contract::query()->value('id'));
});

it('al aceptar, las demás versiones enviadas quedan reemplazadas', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    $a = Fixtures::sentQuote($project);
    $b = Fixtures::sentQuote($project, '120000.00');

    app(ContractService::class)->acceptQuote($b, $user);

    expect($a->refresh()->status)->toBe(QuoteStatus::Superseded);
    expect(fn () => app(ContractService::class)->acceptQuote($a->refresh(), $user))->toThrow(ValidationException::class);
});

it('desde diseño pasa por cotización hasta pendiente de anticipo', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Design);

    app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);

    expect($project->refresh()->status)->toBe(ProjectStatus::PendingDeposit)
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('to_status', 'quoting')->exists())->toBeTrue();
});

it('el crédito de visita se aplica una sola vez aunque haya dos proyectos', function () {
    [$user, $customer] = Fixtures::client();
    $lead = Lead::factory()->create(['customer_id' => $customer->id]);
    $p1 = Fixtures::project($customer, ProjectStatus::Quoting, ['lead_id' => $lead->id]);
    $p2 = Fixtures::project($customer, ProjectStatus::Quoting);
    $credit = Fixtures::credit($customer, '1500.00', ['lead_id' => $lead->id, 'project_id' => $p2->id]);

    $c1 = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($p1), $user);
    $c2 = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($p2), $user);

    $credit->refresh();
    expect($credit->status)->toBe(VisitCreditStatus::Applied)
        ->and($credit->contract_id)->toBe($c1->id)
        ->and($c1->visit_credit_amount)->toBe('1500.00')
        ->and($c2->visit_credit_amount)->toBe('0.00')
        ->and($c2->deposit_due_amount)->toBe('11350.00');
});

it('reintentar la aceptación no vuelve a usar el crédito', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    Fixtures::credit($customer, '1500.00', ['project_id' => $project->id]);
    Fixtures::credit($customer, '1500.00', ['project_id' => $project->id]);
    $quote = Fixtures::sentQuote($project);

    $first = app(ContractService::class)->acceptQuote($quote, $user);
    $second = app(ContractService::class)->acceptQuote($quote->refresh(), $user);

    expect($second->id)->toBe($first->id)
        ->and($second->refresh()->visit_credit_amount)->toBe('1500.00')
        ->and(VisitCredit::query()->where('status', 'applied')->count())->toBe(1);
});

it('no aplica un crédito vencido ni uno de otro cliente', function () {
    [$user, $customer] = Fixtures::client();
    [, $stranger] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    Fixtures::credit($customer, '1500.00', ['project_id' => $project->id], ['expires_on' => now()->subDay()->toDateString()]);
    Fixtures::credit($stranger, '1500.00');

    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);

    expect($contract->visit_credit_amount)->toBe('0.00')
        ->and($contract->deposit_due_amount)->toBe('11350.00');
});

it('si el crédito cubre todo el anticipo el contrato queda activo y el proyecto autorizado', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    Fixtures::credit($customer, '1500.00', ['project_id' => $project->id]);

    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project, '10000.00'), $user);

    expect($contract->refresh()->deposit_amount)->toBe('1000.00')
        ->and($contract->visit_credit_amount)->toBe('1000.00')
        ->and($contract->deposit_due_amount)->toBe('0.00')
        ->and($contract->status)->toBe(ContractStatus::Active)
        ->and($project->refresh()->status)->toBe(ProjectStatus::Authorized);
});

it('cambiar la configuración después no altera el contrato', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);

    Settings::put('commercial', ['deposit_percent' => '30.00']);

    $contract->refresh();
    expect($contract->deposit_percent)->toBe('10.00')
        ->and($contract->deposit_amount)->toBe('11350.00')
        ->and($contract->contract_amount)->toBe('113500.00');
});

it('usa el porcentaje de anticipo vigente al contratar', function () {
    Settings::put('commercial', ['deposit_percent' => '30.00']);
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);

    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);

    expect($contract->deposit_amount)->toBe('34050.00');
});

it('no se acepta una cotización vencida', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    $quote = Fixtures::sentQuote($project, '1000.00', ['valid_until' => now()->subDay()->toDateString()]);

    app(ContractService::class)->acceptQuote($quote, $user);
})->throws(ValidationException::class);

it('confirmar el anticipo autoriza el proyecto exactamente una vez', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    Fixtures::credit($customer, '1500.00', ['project_id' => $project->id]);
    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);

    $payment = Fixtures::payment($customer, PaymentConcept::Deposit, '9850.00', PaymentStatus::Paid, [
        'project_id' => $project->id, 'contract_id' => $contract->id,
    ]);

    event(new PaymentConfirmed($payment));
    event(new PaymentConfirmed($payment));

    $contract->refresh();
    expect($contract->status)->toBe(ContractStatus::Active)
        ->and($contract->deposit_paid_at)->not->toBeNull()
        ->and($project->refresh()->status)->toBe(ProjectStatus::Authorized)
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('to_status', 'authorized')->count())->toBe(1)
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('type', 'deposit_paid')->count())->toBe(1)
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('type', 'payment_received')->count())->toBe(1);
});

it('un pago parcial no autoriza el proyecto', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);

    event(new PaymentConfirmed(Fixtures::payment($customer, PaymentConcept::Deposit, '5000.00', PaymentStatus::Paid, [
        'project_id' => $project->id, 'contract_id' => $contract->id,
    ])));

    expect($contract->refresh()->status)->toBe(ContractStatus::PendingDeposit)
        ->and($project->refresh()->status)->toBe(ProjectStatus::PendingDeposit);
});

it('el reembolso del anticipo marca el contrato sin borrar ni retroceder el proyecto', function () {
    [$user, $customer] = Fixtures::client();
    $project = Fixtures::project($customer, ProjectStatus::Quoting);
    $contract = app(ContractService::class)->acceptQuote(Fixtures::sentQuote($project), $user);
    $payment = Fixtures::payment($customer, PaymentConcept::Deposit, '11350.00', PaymentStatus::Paid, [
        'project_id' => $project->id, 'contract_id' => $contract->id,
    ]);
    event(new PaymentConfirmed($payment));

    $payment->forceFill(['status' => PaymentStatus::Refunded, 'refunded_amount' => '11350.00'])->save();
    $refund = Refund::query()->create([
        'uuid' => (string) Str::uuid(), 'payment_id' => $payment->id, 'idempotency_key' => (string) Str::uuid(),
        'amount' => '11350.00', 'reason' => 'Cliente desistió', 'status' => 'completed',
    ]);
    event(new PaymentRefunded($payment, $refund));

    expect($contract->refresh()->status)->toBe(ContractStatus::DepositRefunded)
        ->and($project->refresh()->status)->toBe(ProjectStatus::Authorized)
        ->and(ProjectEvent::query()->where('project_id', $project->id)->where('type', 'deposit_refunded')->exists())->toBeTrue();
});

it('el equipo registra aceptación a nombre del cliente con motivo', function () {
    $admin = Fixtures::staff();
    $project = Fixtures::project(status: ProjectStatus::Quoting);
    $quote = Fixtures::sentQuote($project);

    $this->actingAs($admin)->post(route('admin.cotizaciones.aceptar', $quote), [])->assertSessionHasErrors('reason');
    $this->actingAs($admin)->post(route('admin.cotizaciones.aceptar', $quote), ['reason' => 'Firmó en sucursal'])->assertRedirect();

    expect($quote->refresh()->accepted_by)->toBe($admin->id)
        ->and(ProjectEvent::query()->where('type', 'quote_accepted')->value('body'))->toBe('Firmó en sucursal');
});
