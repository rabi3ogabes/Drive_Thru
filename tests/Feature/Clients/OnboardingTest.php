<?php

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Plan;
use App\Domain\Growth\Actions\ConvertLead;
use App\Domain\Growth\Actions\SubmitLead;
use App\Domain\Growth\Actions\UpdateLeadStatus;
use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Domain\Growth\Notifications\LeadAcknowledgement;
use App\Domain\Growth\Notifications\NewLeadReceived;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Actions\OnboardClient;
use App\Domain\Tenancy\Data\OnboardClientData;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function clientData(array $overrides = []): OnboardClientData
{
    return new OnboardClientData(...array_merge([
        'name' => ['en' => 'Sahra Burgers', 'ar' => 'صحراء برجر'],
        'ownerName' => 'Mariam Al-Kuwari',
        'ownerEmail' => 'Mariam@Sahra.test',
        'plan' => Plan::factory()->create(),
        'branches' => 2,
    ], $overrides));
}

it('opens a complete client account in one step', function () {
    $admin = User::factory()->superAdmin()->create();

    $result = app(OnboardClient::class)->handle(clientData(), $admin);

    $restaurant = $result['restaurant']->fresh();
    $owner = $result['owner'];

    expect($restaurant->slug)->toBe('sahra-burgers')
        ->and($restaurant->status)->toBe(RestaurantStatus::Trial)
        ->and($restaurant->contact_email)->toBe('mariam@sahra.test')
        ->and($restaurant->positions()->count())->toBe(7)
        ->and($restaurant->subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($restaurant->subscription->quantity)->toBe(2)
        ->and($owner->email)->toBe('mariam@sahra.test')
        ->and($restaurant->owner()->is($owner))->toBeTrue()
        ->and($result['invitation_url'])->toContain('/invitation/')
        ->and(UserInvitation::query()->pending()->where('user_id', $owner->id)->exists())->toBeTrue();

    Notification::assertSentTo($owner, InvitationNotification::class);
});

it('reuses an existing account as owner without a new invitation', function () {
    $existing = User::factory()->create(['email' => 'mariam@sahra.test']);

    $result = app(OnboardClient::class)->handle(clientData());

    expect($result['owner']->is($existing))->toBeTrue()
        ->and($result['invitation_url'])->toBeNull()
        ->and(User::count())->toBe(1);
    Notification::assertNothingSentTo($existing);
});

it('refuses to make platform staff a restaurant owner', function () {
    $staff = User::factory()->create(['email' => 'mariam@sahra.test']);
    $staff->forceFill(['platform_role_id' => PlatformRole::factory()->create()->id])->save();

    app(OnboardClient::class)->handle(clientData());
})->throws(DomainException::class);

it('records website leads, alerts the sales team and thanks the prospect', function () {
    $sales = User::factory()->create();
    $sales->forceFill(['platform_role_id' => PlatformRole::factory()->granting([PlatformPermission::ManageLeads])->create()->id])->save();
    $support = User::factory()->create();
    $support->forceFill(['platform_role_id' => PlatformRole::factory()->granting([PlatformPermission::ViewClients])->create()->id])->save();
    $admin = User::factory()->superAdmin()->create();

    $lead = app(SubmitLead::class)->handle([
        'restaurant_name' => 'Karak House',
        'contact_name' => 'Hamad',
        'email' => 'Hamad@Karak.test',
        'branches_count' => 3,
    ], LeadSource::Website, 'ar', '10.0.0.1');

    expect($lead->status)->toBe(LeadStatus::New)
        ->and($lead->email)->toBe('hamad@karak.test')
        ->and($lead->ip_hash)->not->toBe('10.0.0.1');

    Notification::assertSentTo([$sales, $admin], NewLeadReceived::class);
    Notification::assertNotSentTo($support, NewLeadReceived::class);
    Notification::assertSentTo(new AnonymousNotifiable, LeadAcknowledgement::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'hamad@karak.test');
});

it('merges a repeat request within a day instead of duplicating it', function () {
    $data = ['restaurant_name' => 'Karak House', 'contact_name' => 'Hamad', 'email' => 'hamad@karak.test'];

    app(SubmitLead::class)->handle($data);
    app(SubmitLead::class)->handle($data + ['message' => 'Any update?']);

    expect(Lead::count())->toBe(1)->and(Lead::sole()->message)->toBe('Any update?');
});

it('converts a lead into a client and closes it as won', function () {
    $admin = User::factory()->superAdmin()->create();
    $lead = Lead::factory()->create(['email' => 'mariam@sahra.test']);

    $result = app(ConvertLead::class)->handle($lead, clientData(), $admin);

    $lead->refresh();
    expect($lead->status)->toBe(LeadStatus::Won)
        ->and($lead->converted_restaurant_id)->toBe($result['restaurant']->id)
        ->and($lead->converted_at)->not->toBeNull()
        ->and($lead->notes()->count())->toBe(1);
});

it('enforces the pipeline rules', function () {
    $lead = Lead::factory()->create();
    $action = app(UpdateLeadStatus::class);

    expect(fn () => $action->handle($lead, LeadStatus::Won))->toThrow(DomainException::class)
        ->and(fn () => $action->handle($lead, LeadStatus::Lost))->toThrow(DomainException::class);

    $action->handle($lead, LeadStatus::Contacted);
    expect($lead->fresh()->contacted_at)->not->toBeNull();

    $action->handle($lead, LeadStatus::Lost, lostReason: 'Chose a competitor');
    expect($lead->fresh()->status)->toBe(LeadStatus::Lost)
        ->and($lead->fresh()->lost_reason)->toBe('Chose a competitor')
        ->and($lead->notes()->count())->toBe(2);
});

it('keeps a suspended restaurant open to its owner only', function () {
    $restaurant = restaurant(slug: 'masar');
    $owner = ownerOf($restaurant);
    $cashier = employeeAt($restaurant, 'cashier');
    $restaurant->forceFill(['status' => RestaurantStatus::Suspended])->save();

    $this->actingAs($owner)->get('/app/masar')->assertOk();
    $this->actingAs($cashier)->get('/app/masar')->assertForbidden();

    expect(Employee::withoutTenancy()->count())->toBe(2);
});
