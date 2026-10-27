<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Crm\LeadSubmittedConfirmation;
use App\Notifications\Crm\NewLeadReceived;
use App\Support\Crm\Attribution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\CrmSetup;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Bus::fake();
    Storage::fake('media_private');
    Storage::fake('media_local');
    RateLimiter::clear('crm-leads|127.0.0.1');
    $this->category = CrmSetup::category();
});

it('creates customer, lead with folio and first-touch attribution', function () {
    CrmSetup::zone('06700');
    $sales = User::factory()->role(UserRole::Sales)->create()->refresh();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
        ->withHeader('Referer', 'https://www.google.com/search?q=cocinas')
        ->get('/solicitar?utm_source=facebook&utm_medium=cpc&utm_campaign=cocinas-otono')
        ->assertOk();

    $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148')
        ->post('/solicitar', CrmSetup::leadPayload($this->category));

    $lead = Lead::query()->sole();
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/solicitar/gracias/'.$lead->uuid)->toContain('signature=');

    expect($lead->folio)->toMatch('/^MLH-L-\d{4}-\d{5}$/')
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->utm_source)->toBe('facebook')
        ->and($lead->utm_campaign)->toBe('cocinas-otono')
        ->and($lead->referrer)->toContain('google.com')
        ->and($lead->landing_url)->toContain('/solicitar')
        ->and($lead->device_type)->toBe('mobile')
        ->and($lead->coverage_zone_id)->not->toBeNull()
        ->and($lead->assigned_to)->toBe($sales->id)
        ->and($lead->categoryIds())->toBe([$this->category]);

    $customer = Customer::query()->sole();
    expect($customer->email)->toBe('mariana@example.com')
        ->and($customer->phone)->toBe('5512345678')
        ->and($customer->privacy_accepted_at)->not->toBeNull();

    expect($lead->activities()->where('type', LeadActivityType::Created->value)->exists())->toBeTrue();
    Notification::assertSentTo($sales, NewLeadReceived::class);
    Notification::assertSentOnDemand(LeadSubmittedConfirmation::class);
});

it('reuses an existing customer by phone without overwriting data', function () {
    $existing = Customer::factory()->create(['first_name' => 'Mariana', 'email' => 'otro@example.com', 'phone' => '5512345678']);

    $this->post('/solicitar', CrmSetup::leadPayload($this->category))->assertRedirect();

    expect(Customer::query()->count())->toBe(1);
    $existing->refresh();
    expect($existing->email)->toBe('otro@example.com')
        ->and($existing->notes)->toContain('mariana@example.com');
});

it('ignores honeypot submissions silently', function () {
    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['website' => 'http://spam.example']))
        ->assertRedirect(route('public.solicitar'));

    expect(Lead::query()->count())->toBe(0)->and(Customer::query()->count())->toBe(0);
});

it('requires privacy consent', function () {
    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['privacy' => null]))
        ->assertSessionHasErrors('privacy');

    expect(Lead::query()->count())->toBe(0);
});

it('validates phone to 10 digits', function () {
    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['phone' => '12345']))
        ->assertSessionHasErrors('phone');
});

it('is idempotent for a double submit with the same submission key', function () {
    $payload = CrmSetup::leadPayload($this->category);

    $first = $this->post('/solicitar', $payload);
    $second = $this->post('/solicitar', $payload);

    expect(Lead::query()->count())->toBe(1)
        ->and($second->headers->get('Location'))->toBe($first->headers->get('Location'));
});

it('rate limits submissions per IP', function () {
    config(['crm.lead_submissions_per_hour' => 2]);

    $this->post('/solicitar', CrmSetup::leadPayload($this->category))->assertRedirect();
    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['phone' => '5511111111', 'email' => 'b@example.com']))->assertRedirect();
    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['phone' => '5522222222', 'email' => 'c@example.com']))->assertSessionHasErrors('form');

    expect(Lead::query()->count())->toBe(2);
});

it('stores up to six private photos attached to the lead', function () {
    $this->post('/solicitar', CrmSetup::leadPayload($this->category, [
        'photos' => [UploadedFile::fake()->image('cocina.jpg', 800, 600), UploadedFile::fake()->image('pared.png', 400, 400)],
    ]))->assertRedirect();

    $lead = Lead::query()->sole();
    $photos = $lead->mediaWithRole(Lead::PHOTO_ROLE)->get();

    expect($photos)->toHaveCount(2)
        ->and($photos->every(fn ($m) => $m->isPrivate() && $m->disk === 'media_private'))->toBeTrue();
    Storage::disk('media_private')->assertExists($photos->first()->path);
});

it('rejects photos whose real content is not an image', function () {
    $fake = UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo "hola"; ?>');

    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['photos' => [$fake]]))
        ->assertSessionHasErrors('photos.0');

    expect(Lead::query()->count())->toBe(0);
});

it('rejects more than six photos', function () {
    $photos = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg", 10, 10), range(1, 7));

    $this->post('/solicitar', CrmSetup::leadPayload($this->category, ['photos' => $photos]))
        ->assertSessionHasErrors('photos');
});

it('shows the thank-you page only with a valid signature', function () {
    $this->post('/solicitar', CrmSetup::leadPayload($this->category));
    $lead = Lead::query()->sole();

    $this->get('/solicitar/gracias/'.$lead->uuid)->assertForbidden();
});

it('prefills the category from the query string', function () {
    $slug = DB::table('product_categories')->where('id', $this->category)->value('slug');

    $this->get('/solicitar?categoria='.$slug)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/solicitar/Create')->where('preselected', [$this->category]));
});

it('classifies device types from the user agent', function () {
    expect(Attribution::deviceType('Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X)'))->toBe('tablet')
        ->and(Attribution::deviceType('Mozilla/5.0 (Linux; Android 14; Pixel 8) Mobile Safari'))->toBe('mobile')
        ->and(Attribution::deviceType('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'))->toBe('desktop');
});
