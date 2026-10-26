<?php

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\PlanCatalog;
use App\Domain\Growth\Models\Lead;
use App\Domain\Platform\Models\Announcement;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Livewire\WorkspaceNotices;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    app(PlanCatalog::class)->install();
});

it('turns the website form into a lead', function () {
    $this->post('/request-access', [
        'restaurant_name' => 'Al Wakra Shawarma',
        'contact_name' => 'Saeed',
        'email' => 'saeed@wakra.test',
        'phone' => '+974 5512 3456',
        'branches_count' => 3,
        'plan' => 'growth',
        'message' => 'Two lanes, busy at night.',
    ])->assertRedirect(route('home').'#request')->assertSessionHas('lead_submitted');

    $lead = Lead::sole();

    expect($lead->restaurant_name)->toBe('Al Wakra Shawarma')
        ->and($lead->branches_count)->toBe(3)
        ->and($lead->plan->key)->toBe('growth');
});

it('validates the form in the visitor’s language', function () {
    $this->withHeader('Accept-Language', 'ar')
        ->from('/')
        ->post('/request-access', ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['restaurant_name', 'contact_name', 'email']);

    expect(session('errors')->first('email'))->toContain('البريد');
});

it('silently drops bots that fill the honeypot', function () {
    $this->post('/request-access', [
        'restaurant_name' => 'Spam', 'contact_name' => 'Bot', 'email' => 'bot@spam.test', 'company_website' => 'http://spam.test',
    ])->assertRedirect()->assertSessionHas('lead_submitted');

    expect(Lead::count())->toBe(0);
});

it('rate limits the form', function () {
    RateLimiter::clear('leads:127.0.0.1');

    foreach (range(1, 3) as $i) {
        $this->post('/request-access', ['restaurant_name' => "R{$i}", 'contact_name' => 'C', 'email' => "c{$i}@x.test"])->assertRedirect();
    }

    $this->post('/request-access', ['restaurant_name' => 'R4', 'contact_name' => 'C', 'email' => 'c4@x.test'])->assertStatus(429);
});

it('lets an invited owner choose a password and lands them in their workspace', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = employeeAt($restaurant, attributes: ['is_owner' => true, 'status' => EmployeeStatus::Invited]);
    $url = app(InviteUser::class)->handle($owner, sendEmail: false);
    $token = basename($url);

    $this->get($url)->assertOk()->assertSee($owner->email);

    $this->post("/invitation/{$token}", ['password' => 'Crescent-Moon-2026', 'password_confirmation' => 'Crescent-Moon-2026'])
        ->assertRedirect('/app/masar');

    expect(auth()->id())->toBe($owner->id)
        ->and($owner->fresh()->email_verified_at)->not->toBeNull()
        ->and($owner->employmentAt($restaurant)?->status)->toBe(EmployeeStatus::Active);

    // Single use: the same link is now expired.
    auth()->logout();
    $this->get($url)->assertStatus(410)->assertSee(__('access.invitation.expired.title'));
});

it('rejects expired and replaced invitation links', function () {
    $user = User::factory()->create();
    $first = app(InviteUser::class)->handle($user, sendEmail: false);
    $second = app(InviteUser::class)->handle($user, sendEmail: false);

    $this->get($first)->assertStatus(410);
    $this->get($second)->assertOk();

    $this->travel(8)->days();
    $this->get($second)->assertStatus(410);

    expect(UserInvitation::query()->pending()->count())->toBe(0);
});

it('shows workspace notices: suspension, banner and dismissible announcements', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $announcement = Announcement::factory()->create(['title' => ['en' => 'Arrival alerts are live', 'ar' => 'تنبيهات الوصول متاحة']]);
    app(PlatformSettings::class)->set(['banner.enabled' => true, 'banner.message' => ['en' => 'Maintenance at 2am', 'ar' => 'صيانة الساعة 2']]);
    $restaurant->forceFill(['status' => RestaurantStatus::Suspended])->save();

    $this->actingAs($owner)->withHeader('Accept-Language', 'en')->get('/app/masar')
        ->assertSee(__('clients.notices.suspended.title', [], 'en'))
        ->assertSee('Maintenance at 2am')
        ->assertSee('Arrival alerts are live');

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    Livewire::actingAs($owner)->test(WorkspaceNotices::class)->call('dismiss', $announcement->id);

    expect($announcement->dismissedBy()->whereKey($owner->id)->exists())->toBeTrue();
});

it('shows the owner their billing page, and only the owner', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $cashier = employeeAt($restaurant, 'cashier');
    withService($restaurant, Service::DriveThru, priceMinor: 34900);
    app(StartSubscription::class)->handle($restaurant, Plan::where('key', 'growth')->sole(), trialDays: 0);

    $this->actingAs($owner)->get('/app/masar/billing')->assertOk()->assertSee('DTP-');
    $this->actingAs($cashier)->get('/app/masar/billing')->assertForbidden();
});
