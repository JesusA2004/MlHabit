<?php

use App\Cms\Sections\PaymentOptionsSection;
use App\Enums\SiteSectionType;
use App\Models\Media;
use App\Services\Web\PublicSite;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

it('validates the default content of every section type', function (SiteSectionType $type) {
    $schema = $type->schema();

    expect($schema->validate($schema->defaults()))->toBe($schema->normalize($schema->defaults()))
        ->and($schema->fields())->not->toBeEmpty();
})->with(SiteSectionType::curated());

it('rejects invalid hero content and unsafe links', function () {
    $schema = SiteSectionType::Hero->schema();

    expect(fn () => $schema->validate([...$schema->defaults(), 'title' => '']))->toThrow(ValidationException::class);
    expect(fn () => $schema->validate([...$schema->defaults(), 'primary_cta_href' => 'javascript:alert(1)']))->toThrow(ValidationException::class);
    expect(fn () => $schema->validate([...$schema->defaults(), 'primary_cta_href' => '//evil.example']))->toThrow(ValidationException::class);
    expect($schema->validate([...$schema->defaults(), 'primary_cta_href' => 'https://wa.me/525512345678'])['primary_cta_href'])->toBe('https://wa.me/525512345678');
});

it('never allows credit approval promises in payment options', function () {
    $schema = SiteSectionType::PaymentOptions->schema();

    expect(fn () => $schema->validate([...$schema->defaults(), 'title' => 'Crédito aprobado en minutos']))
        ->toThrow(ValidationException::class);
    expect(fn () => $schema->validate([...$schema->defaults(), 'disclaimer' => '']))->toThrow(ValidationException::class);
    expect(preg_match(PaymentOptionsSection::FORBIDDEN, 'Aprobación garantizada'))->toBe(1);
});

it('only accepts public images as section media', function () {
    $schema = SiteSectionType::Hero->schema();
    $public = Media::factory()->create();
    $private = Media::factory()->private()->create();

    expect($schema->validate([...$schema->defaults(), 'media_id' => $public->id])['media_id'])->toBe($public->id);
    expect(fn () => $schema->validate([...$schema->defaults(), 'media_id' => $private->id]))->toThrow(ValidationException::class);
    expect($schema->mediaIds(['media_id' => $public->id]))->toBe([$public->id]);
});

it('validates list items and drops unknown keys', function () {
    $schema = SiteSectionType::Process->schema();

    expect(fn () => $schema->validate([...$schema->defaults(), 'steps' => []]))->toThrow(ValidationException::class);
    expect(fn () => $schema->validate([...$schema->defaults(), 'steps' => [['title' => '', 'body' => 'x']]]))->toThrow(ValidationException::class);

    $clean = $schema->validate([...$schema->defaults(), 'hacker' => '<script>', 'steps' => [['title' => 'Uno', 'body' => 'Dos', 'extra' => 1]]]);
    expect($clean)->not->toHaveKey('hacker')
        ->and($clean['steps'])->toBe([['title' => 'Uno', 'body' => 'Dos']]);
});

it('forbids the site settings to staff without content permissions', function () {
    $user = WebFixtures::staff(['dashboard.view', 'leads.view']);

    $this->actingAs($user)->get(route('admin.sitio.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.sitio.editor.edit'))->assertForbidden();
});

it('renders the site settings with the visual editor summary', function () {
    WebFixtures::sections();

    $this->actingAs(WebFixtures::admin())
        ->get(route('admin.sitio.index', ['pestana' => 'identidad']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/sitio/Index')
            ->where('tab', 'identidad')
            ->where('editor.sections', 11)
            ->where('editor.hasChanges', false)
            ->where('editor.lastVersion', null));
});

it('saves contact settings and hides empty values on the public site', function () {
    $admin = WebFixtures::admin();

    $this->actingAs($admin)
        ->put(route('admin.sitio.ajustes.update', 'contact'), ['phone' => '55 1234 5678', 'email' => '', 'whatsapp' => '5512345678'])
        ->assertSessionHasNoErrors();

    $site = app(PublicSite::class)->site();
    expect($site['contact'])->toHaveKey('phone')
        ->and($site['contact'])->not->toHaveKey('email')
        ->and($site['contact']['whatsappHref'])->toBe('https://wa.me/525512345678');
});
