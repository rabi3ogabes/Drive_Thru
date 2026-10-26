<?php

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\PlanCatalog;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Domain\Platform\Models\Announcement;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\Pages\PlatformSettingsPage;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Leads\Pages\ViewLead;
use App\Filament\Admin\Resources\Restaurants\Pages\CreateRestaurant;
use App\Filament\Admin\Resources\Restaurants\Pages\ViewRestaurant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    app(PlanCatalog::class)->install();
    $this->admin = User::factory()->superAdmin()->create(['name' => 'Noor Al-Thani']);
});

function clientWithInvoice(): Restaurant
{
    $restaurant = restaurant(slug: 'masar');
    ownerOf($restaurant);
    withService($restaurant, Service::DriveThru, priceMinor: 34900); // services carry the price
    app(StartSubscription::class)->handle($restaurant, Plan::where('key', 'growth')->sole(), 2, trialDays: 0);

    return $restaurant->fresh();
}

function staffWith(array $permissions): User
{
    $user = User::factory()->create();
    $user->forceFill(['platform_role_id' => PlatformRole::factory()->granting($permissions)->create()->id])->save();

    return $user;
}

it('renders every console screen for the super admin', function (string $path) {
    clientWithInvoice();
    Lead::factory()->create();
    SecurityAlert::create(['type' => 'account_locked', 'attempted_email' => 'ab***@x.test', 'attempt_count' => 5]);
    Announcement::factory()->create();

    $this->actingAs($this->admin)->get($path)->assertOk();
})->with([
    'dashboard' => '/admin',
    'clients' => '/admin/restaurants',
    'new client wizard' => '/admin/restaurants/create',
    'client page' => '/admin/restaurants/masar',
    'edit client' => '/admin/restaurants/masar/edit',
    'leads' => '/admin/leads',
    'new lead' => '/admin/leads/create',
    'invoices' => '/admin/invoices',
    'plans' => '/admin/plans',
    'new plan' => '/admin/plans/create',
    'team' => '/admin/team',
    'invite member' => '/admin/team/create',
    'roles' => '/admin/roles',
    'announcements' => '/admin/announcements',
    'security alerts' => '/admin/security/alerts',
    'sessions' => '/admin/security/sessions',
    'audit log' => '/admin/audit-log',
    'settings' => '/admin/settings',
]);

it('shows each platform role only what it is allowed to see', function () {
    clientWithInvoice();
    $support = staffWith([PlatformPermission::ViewClients, PlatformPermission::ImpersonateOwners, PlatformPermission::ViewSecurity]);

    $this->actingAs($support);
    $this->get('/admin/restaurants')->assertOk();
    $this->get('/admin/restaurants/masar')->assertOk();
    $this->get('/admin/security/alerts')->assertOk();

    foreach (['/admin/restaurants/create', '/admin/invoices', '/admin/plans', '/admin/leads', '/admin/team', '/admin/roles', '/admin/settings', '/admin/audit-log', '/admin/announcements'] as $forbidden) {
        $this->get($forbidden)->assertForbidden();
    }
});

it('opens a client through the wizard', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => ['en' => 'Sahra Burgers', 'ar' => 'صحراء برجر'],
            'slug' => 'sahra-burgers',
            'default_locale' => 'ar',
            'timezone' => 'Asia/Qatar',
            'owner_name' => 'Mariam Al-Kuwari',
            'owner_email' => 'mariam@sahra.test',
            'owner_phone' => '55123456',
            'send_invitation' => true,
            'plan_id' => Plan::where('key', 'starter')->value('id'),
            'branches' => 1,
            'trial_days' => 14,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/restaurants/sahra-burgers');

    $restaurant = Restaurant::where('slug', 'sahra-burgers')->sole();

    expect($restaurant->status)->toBe(RestaurantStatus::Trial)
        ->and($restaurant->owner()->email)->toBe('mariam@sahra.test')
        ->and($restaurant->getTranslation('name', 'ar'))->toBe('صحراء برجر');
});

it('refuses reserved or taken links in the wizard', function (string $slug) {
    restaurant(slug: 'masar');
    $this->actingAs($this->admin);

    Livewire::test(CreateRestaurant::class)
        ->fillForm([
            'name' => ['en' => 'X', 'ar' => 'س'],
            'slug' => $slug,
            'owner_name' => 'A',
            'owner_email' => 'a@b.test',
            'plan_id' => Plan::where('key', 'starter')->value('id'),
            'branches' => 1,
            'trial_days' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);
})->with(['taken' => 'masar', 'reserved' => 'admin']);

it('converts a lead from its page', function () {
    $lead = Lead::factory()->create(['restaurant_name' => 'Karak House', 'email' => 'hamad@karak.test', 'contact_name' => 'Hamad']);
    $this->actingAs($this->admin);

    Livewire::test(ViewLead::class, ['record' => $lead->getKey()])
        ->callAction('convert', data: [
            'name' => ['en' => 'Karak House', 'ar' => 'بيت الكرك'],
            'slug' => 'karak-house',
            'plan_id' => Plan::where('key', 'starter')->value('id'),
            'branches' => 2,
            'trial_days' => 14,
        ])
        ->assertHasNoActionErrors();

    expect($lead->fresh()->status)->toBe(LeadStatus::Won)
        ->and(Restaurant::where('slug', 'karak-house')->exists())->toBeTrue();
});

it('records a payment from the invoices list', function () {
    clientWithInvoice();
    $invoice = Invoice::withoutTenancy()->sole();
    $this->actingAs($this->admin);

    Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$invoice])
        ->callTableAction('markPaid', $invoice, data: ['method' => 'bank_transfer', 'reference' => 'TRX-77', 'paid_at' => now()->toDateTimeString()])
        ->assertHasNoTableActionErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->payment_reference)->toBe('TRX-77');
});

it('downloads invoices only for billing staff and the client’s own owner', function () {
    $restaurant = clientWithInvoice();
    $invoice = Invoice::withoutTenancy()->sole();
    $stranger = ownerOf(restaurant(['en' => 'Other', 'ar' => 'آخر'], slug: 'other'));

    $this->actingAs($this->admin)->get(route('invoices.pdf', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($restaurant->owner())->get(route('invoices.pdf', $invoice))->assertOk();
    $this->actingAs($stranger)->get(route('invoices.pdf', $invoice))->assertForbidden();
    $this->actingAs(staffWith([PlatformPermission::ViewClients]))->get(route('invoices.pdf', $invoice))->assertForbidden();
});

it('saves platform settings', function () {
    $this->actingAs($this->admin);

    Livewire::test(PlatformSettingsPage::class)
        ->fillForm([
            'billing' => ['trial_days' => 21, 'invoice_due_days' => 10, 'grace_days' => 5, 'tax_rate_percent' => 5, 'currency' => 'QAR', 'legal_name' => 'Drive-Thru Plus W.L.L.'],
            'banner' => ['enabled' => true, 'level' => 'warning', 'message' => ['en' => 'Maintenance tonight', 'ar' => 'صيانة الليلة']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(PlatformSettings::class);

    expect($settings->int('billing.trial_days'))->toBe(21)
        ->and($settings->int('billing.tax_rate_bp'))->toBe(500)
        ->and($settings->bool('banner.enabled'))->toBeTrue()
        ->and($settings->get('banner.message')['ar'])->toBe('صيانة الليلة');
});

it('lets support view a workspace as its owner, safely and on the record', function () {
    $restaurant = clientWithInvoice();
    $owner = $restaurant->owner();
    $support = staffWith([PlatformPermission::ViewClients, PlatformPermission::ImpersonateOwners]);
    $this->actingAs($support);

    Livewire::test(ViewRestaurant::class, ['record' => $restaurant->slug])
        ->callAction('viewAsOwner', data: ['reason' => 'Helping with menu setup'])
        ->assertRedirect('/app/masar');

    expect(auth()->id())->toBe($owner->id);

    $this->get('/app/masar')->assertOk()->assertSee(__('clients.impersonation.leave'));
    $this->get('/app/profile')->assertForbidden();
    expect($owner->fresh()->last_login_at)->toBe($owner->last_login_at);

    $this->post('/impersonation/leave')->assertRedirect('/admin/restaurants/masar');

    expect(auth()->id())->toBe($support->id)
        ->and(Activity::where('event', 'impersonation.started')->first()->properties['reason'])->toBe('Helping with menu setup')
        ->and(Activity::where('event', 'impersonation.ended')->exists())->toBeTrue();
});

it('never lets staff without the permission view as owner', function () {
    $restaurant = clientWithInvoice();
    $this->actingAs(staffWith([PlatformPermission::ViewClients]));

    Livewire::test(ViewRestaurant::class, ['record' => $restaurant->slug])
        ->assertActionHidden('viewAsOwner');
});

it('labels the navigation in the chosen language', function (string $locale, string $expected, string $unexpected) {
    $this->admin->forceFill(['locale' => $locale])->save();

    $this->actingAs($this->admin)
        ->withHeader('Accept-Language', $locale === 'en' ? 'ar' : 'en')
        ->get('/admin')
        ->assertOk()
        ->assertSee($expected)
        ->assertDontSee($unexpected);
})->with([
    'english' => ['en', 'Clients', 'العملاء'],
    'arabic' => ['ar', 'العملاء', '>Clients<'],
]);

it('shows live order activity on the dashboard to platform staff who look after clients', function () {
    $access = app(ChannelAccess::class);

    $this->actingAs($this->admin)->get('/admin')->assertOk()
        ->assertSee('data-live-channel="platform.dashboard"', false)
        ->assertSee(__('dashboard.live.orders_24h'));

    expect($access->platformDashboard($this->admin))->toBeTrue()
        ->and($access->platformDashboard(staffWith([PlatformPermission::ViewClients])))->toBeTrue()
        ->and($access->platformDashboard(staffWith([PlatformPermission::ManageLeads])))->toBeFalse()
        ->and($access->platformDashboard(ownerOf(restaurant())))->toBeFalse();
});
