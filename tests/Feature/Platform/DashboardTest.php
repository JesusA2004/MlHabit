<?php

use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
    // Mediodía a mitad de mes: evita bordes de día/mes en las ventanas.
    $now = CarbonImmutable::parse('2026-10-15 12:00:00');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);
});

afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

test('guests go to login and customers or technicians are forbidden', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->actingAs(F::user(UserRole::Customer))->get('/admin')->assertForbidden();
    $this->actingAs(F::user(UserRole::Technician))->get('/admin')->assertForbidden();
});

test('the dashboard renders every block for a super admin', function () {
    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('sections.commercial', true)
            ->where('sections.finance', true)
            ->whereNot('commercial', null)
            ->whereNot('visits', null)
            ->whereNot('projects', null)
            ->whereNot('finance', null)
            ->whereNot('operations', null)
            ->whereNot('agenda', null)
            ->where('finance.collectedThisMonth.net', '0.00'));
});

test('a comercial user only receives the blocks they are allowed to see', function () {
    $this->actingAs(F::user(UserRole::Sales))
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.commercial', true)
            ->where('sections.visits', true)
            ->where('sections.projects', true)
            // comercial tiene payments.view por defecto, pero no incidencias ni garantías.
            ->where('sections.incidents', false)
            ->where('sections.warranties', false)
            ->where('operations', null)
            ->whereNot('commercial', null));
});

test('blocks follow the role permissions, including finance', function () {
    $this->actingAs(F::user(UserRole::Design))
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('commercial', null)
            ->where('finance', null)
            ->whereNot('projects', null));
});

test('the dashboard numbers match the stored data', function () {
    $advisor = F::user(UserRole::Sales);
    $now = CarbonImmutable::now();
    $customer = F::customer();

    // Prospectos: 3 en los últimos 7 días (uno sin contactar desde hace 30 h), 1 entre 8 y 14 días.
    $uncontacted = F::lead($customer, $now->subHours(30));
    F::lead($customer, $now->subDays(2), 'contacted', Carbon::now()->subDay());
    F::lead($customer, $now->subHours(2)); // nuevo, aún dentro de las 24 h
    F::lead($customer, $now->subDays(10), 'contacted', Carbon::now()->subDays(9));
    F::lead($customer, $now->subDays(40));
    Settings::put('leads', ['follow_up_hours' => 24]);

    // Proyectos: 1 contratado en 30 días, otros activos por etapa.
    $p1 = F::project($customer, 'fabrication', Carbon::now()->subDays(5));
    F::project($customer, 'pending_deposit');
    F::project($customer, 'installing', Carbon::now()->subDays(60));
    F::project($customer, 'completed', Carbon::now()->subDays(90));
    F::project($customer, 'cancelled');

    // Pagos del mes: 1000 pagado, 500 parcialmente reembolsado (200), uno fuera del mes, uno fallido.
    F::payment($customer, '1000.00', 'paid', Carbon::now()->subDays(3));
    $partial = F::payment($customer, '500.00', 'partially_refunded', Carbon::now()->subDays(2), '200.00');
    F::payment($customer, '9999.99', 'paid', Carbon::now()->subMonths(2));
    F::payment($customer, '300.00', 'failed', null, '0.00', Carbon::now()->subDays(1));
    F::refund($partial, '200.00', Carbon::now()->subDay());

    // Asesoría de hoy y operación.
    F::consultation($uncontacted, $advisor->id, Carbon::parse('2026-10-15 17:00:00'));
    F::consultation($uncontacted, $advisor->id, Carbon::parse('2026-10-15 09:30:00'), 'cancelled');
    F::incident($p1, 'critical');
    F::incident($p1, 'low');
    F::incident($p1, 'high', 'resolved');
    F::warranty($p1, 'active', Carbon::now()->addYear());
    F::warranty($p1, 'active', Carbon::now()->subDay());

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('commercial.newLeads.7.current', 3)
            ->where('commercial.newLeads.7.previous', 1)
            ->where('commercial.newLeads.7.delta', 2)
            ->where('commercial.newLeads.30.current', 4)
            ->where('commercial.uncontacted', 2)
            ->where('commercial.conversion.contracted', 1)
            ->where('commercial.conversion.leads', 4)
            ->where('commercial.conversion.rate', '25.0')
            ->where('commercial.consultationsToday', 1)
            ->has('commercial.trend', 30)
            ->where('projects.active', 3)
            ->where('projects.pendingDeposit', 1)
            ->where('projects.inFabrication', 1)
            ->where('projects.inInstallation', 1)
            ->where('finance.collectedThisMonth.gross', '1500.00')
            ->where('finance.collectedThisMonth.net', '1300.00')
            ->where('finance.collectedThisMonth.payments', 2)
            ->where('finance.failedLast7', 1)
            ->where('finance.refundsThisMonth.amount', '200.00')
            ->where('finance.refundsThisMonth.count', 1)
            ->where('operations.incidents.open', 2)
            ->where('operations.incidents.bySeverity.0.severity', 'critical')
            ->where('operations.incidents.bySeverity.0.count', 1)
            ->where('operations.activeWarranties', 1)
            ->has('agenda', 1)
            ->where('agenda.0.kind', 'consultation')
            ->where('agenda.0.title', 'Ana Prueba')
            ->where('attention', fn ($items) => collect($items)->pluck('count', 'key')->only(['leads_uncontacted', 'payments_failed', 'incidents_high', 'projects_deposit'])->all() === [
                'leads_uncontacted' => 2,
                'payments_failed' => 1,
                'incidents_high' => 1,
                'projects_deposit' => 1,
            ]));
});

test('a staff member without dashboard.view still lands on a minimal dashboard', function () {
    RolePermission::sync(UserRole::Quality, []);

    $this->actingAs(F::user(UserRole::Quality))
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.commercial', false)
            ->where('commercial', null)
            ->where('attention', []));
});
