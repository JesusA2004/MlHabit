<?php

use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('forbids lead screens to staff without leads.view', function () {
    $finance = User::factory()->role(UserRole::Finance)->create()->refresh();
    $lead = Lead::factory()->create();

    $this->actingAs($finance)->get(route('admin.prospectos.index'))->assertForbidden();
    $this->actingAs($finance)->get(route('admin.prospectos.show', $lead))->assertForbidden();
});

it('keeps customers and technicians out of /admin', function (UserRole $role) {
    $user = User::factory()->role($role)->create()->refresh();

    $this->actingAs($user)->get(route('admin.prospectos.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.clientes.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.agenda.index'))->assertForbidden();
})->with([UserRole::Customer, UserRole::Technician]);

it('redirects guests to login', function () {
    $this->get(route('admin.prospectos.index'))->assertRedirect(route('login'));
});

it('lets sales see leads, customers and consultations', function () {
    $sales = User::factory()->role(UserRole::Sales)->create()->refresh();
    $lead = Lead::factory()->create();
    $consultation = Consultation::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($sales)->get(route('admin.prospectos.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/prospectos/Index')->has('leads.data', 1));
    $this->actingAs($sales)->get(route('admin.prospectos.show', $lead))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/prospectos/Show')->where('lead.folio', $lead->folio));
    $this->actingAs($sales)->get(route('admin.clientes.show', $lead->customer))->assertOk();
    $this->actingAs($sales)->get(route('admin.asesorias.show', $consultation))->assertOk();
});

it('restricts leads to the assignee when visibility is "assigned"', function () {
    config(['crm.lead_visibility' => 'assigned']);
    $sales = User::factory()->role(UserRole::Sales)->create()->refresh();
    $other = User::factory()->role(UserRole::Sales)->create()->refresh();
    $mine = Lead::factory()->create(['assigned_to' => $sales->id]);
    $theirs = Lead::factory()->create(['assigned_to' => $other->id]);

    $this->actingAs($sales)->get(route('admin.prospectos.show', $mine))->assertOk();
    $this->actingAs($sales)->get(route('admin.prospectos.show', $theirs))->assertForbidden();
    $this->actingAs($sales)->get(route('admin.prospectos.index'))
        ->assertInertia(fn ($page) => $page->has('leads.data', 1)->where('leads.data.0.folio', $mine->folio));
});

it('requires customers.manage to edit a customer', function () {
    $credit = User::factory()->role(UserRole::Credit)->create()->refresh();
    $customer = Customer::factory()->create();

    $this->actingAs($credit)->patch(route('admin.clientes.update', $customer), ['first_name' => 'X'])->assertForbidden();
});

it('requires schedule.manage to edit availability', function () {
    $sales = User::factory()->role(UserRole::Sales)->create()->refresh();

    $this->actingAs($sales)->put(route('admin.agenda.horario', $sales), [
        'kind' => 'consultation', 'ranges' => [['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']],
    ])->assertForbidden();
});
