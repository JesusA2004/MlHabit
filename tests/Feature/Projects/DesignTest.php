<?php

use App\Enums\DesignStatus;
use App\Enums\MediaVisibility;
use App\Enums\UserRole;
use App\Models\DesignVersion;
use App\Models\Media;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\DesignService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

function designWithRender(Project $project, User $designer): DesignVersion
{
    $design = app(DesignService::class)->create($project, ['title' => 'Distribución en L'], $designer);
    $media = Media::factory()->create(['visibility' => MediaVisibility::Private, 'uploaded_by' => $designer->id]);
    test()->actingAs($designer)->post(route('admin.disenos.archivos.store', $design), ['media_id' => $media->id, 'role' => 'render'])->assertSessionHas('success');

    return $design->refresh();
}

it('numera versiones y reemplaza las anteriores', function () {
    $designer = Fixtures::staff(UserRole::Design);
    $project = Fixtures::project();
    $service = app(DesignService::class);

    $v1 = $service->create($project, ['title' => 'Propuesta A'], $designer);
    $v2 = $service->create($project, ['title' => 'Propuesta B'], $designer);

    expect($v1->refresh()->status)->toBe(DesignStatus::Superseded)
        ->and($v2->number)->toBe(2);
});

it('editar una versión enviada crea la siguiente con sus archivos', function () {
    $designer = Fixtures::staff(UserRole::Design);
    $project = Fixtures::project();
    $v1 = designWithRender($project, $designer);
    app(DesignService::class)->send($v1, $designer);

    $this->actingAs($designer)->put(route('admin.disenos.update', $v1), ['title' => 'Ajuste de alacena'])->assertSessionHas('success');

    $v2 = DesignVersion::query()->where('project_id', $project->id)->where('number', 2)->firstOrFail();
    expect($v1->refresh()->status)->toBe(DesignStatus::Superseded)
        ->and($v1->title)->toBe('Distribución en L')
        ->and($v2->attachments()->where('role', 'render')->count())->toBe(1);
});

it('no se envía sin renders', function () {
    $designer = Fixtures::staff(UserRole::Design);
    $design = app(DesignService::class)->create(Fixtures::project(), ['title' => 'Sin archivos'], $designer);

    app(DesignService::class)->send($design, $designer);
})->throws(ValidationException::class);

it('la aprobación del cliente no equivale a la aprobación técnica', function () {
    $designer = Fixtures::staff(UserRole::Design);
    [$client, $customer] = Fixtures::client();
    $project = Fixtures::project($customer);
    $design = designWithRender($project, $designer);
    app(DesignService::class)->send($design, $designer);

    $this->actingAs($client)->post(route('portal.disenos.decision', $design), ['decision' => 'approve', 'layout' => true, 'finishes' => false])
        ->assertSessionHasErrors('layout');
    $this->actingAs($client)->post(route('portal.disenos.decision', $design), ['decision' => 'approve', 'layout' => true, 'finishes' => true])
        ->assertSessionHas('success');

    $design->refresh();
    expect($design->status)->toBe(DesignStatus::ClientApproved)
        ->and($design->technical_approved_at)->toBeNull();

    // Ventas no tiene designs.approve_technical.
    $this->actingAs(Fixtures::staff(UserRole::Sales))->post(route('admin.disenos.aprobacion-tecnica', $design))->assertForbidden();

    $this->actingAs($designer)->post(route('admin.disenos.aprobacion-tecnica', $design), ['notes' => 'Medidas verificadas'])->assertSessionHas('success');
    $design->refresh();
    expect($design->status)->toBe(DesignStatus::TechnicallyApproved)
        ->and($design->technical_approved_by)->toBe($designer->id);
});

it('la aprobación técnica exige aprobación previa del cliente', function () {
    $designer = Fixtures::staff(UserRole::Design);
    $design = designWithRender(Fixtures::project(), $designer);

    app(DesignService::class)->approveTechnical($design, $designer, null);
})->throws(ValidationException::class);

it('pedir cambios exige comentario y otro cliente no puede decidir', function () {
    $designer = Fixtures::staff(UserRole::Design);
    [$client, $customer] = Fixtures::client();
    [$stranger] = Fixtures::client();
    $design = designWithRender(Fixtures::project($customer), $designer);
    app(DesignService::class)->send($design, $designer);

    $this->actingAs($stranger)->post(route('portal.disenos.decision', $design), ['decision' => 'changes', 'comment' => 'x'])->assertNotFound();
    $this->actingAs($client)->post(route('portal.disenos.decision', $design), ['decision' => 'changes'])->assertSessionHasErrors('comment');
    $this->actingAs($client)->post(route('portal.disenos.decision', $design), ['decision' => 'changes', 'comment' => 'Quiero más cajones'])->assertSessionHas('success');

    expect($design->refresh()->status)->toBe(DesignStatus::ChangesRequested);
});

it('no permite adjuntar archivos subidos por otra persona', function () {
    $designer = Fixtures::staff(UserRole::Design);
    $design = app(DesignService::class)->create(Fixtures::project(), ['title' => 'A'], $designer);
    $foreign = Media::factory()->create(['visibility' => MediaVisibility::Private, 'uploaded_by' => Fixtures::staff()->id]);

    $this->actingAs($designer)->post(route('admin.disenos.archivos.store', $design), ['media_id' => $foreign->id, 'role' => 'render'])
        ->assertSessionHasErrors('media_id');
});
