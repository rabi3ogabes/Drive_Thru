<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Access\Models\Position;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Campaigns\Listeners\AttributeCampaignOrder;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Crm\Listeners\RefreshGuestStats;
use App\Domain\Customers\CustomerAuth;
use App\Domain\Customers\Models\Customer;
use App\Domain\Growth\Models\Lead;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Integrations\Pos\SendAcceptedOrdersToPos;
use App\Domain\Integrations\Webhooks\Listeners\SendWebhooks;
use App\Domain\Loyalty\Listeners\AwardPointsOnPickup;
use App\Domain\Loyalty\Listeners\ReturnBenefitsOnCancel;
use App\Domain\Loyalty\Listeners\ReversePointsOnRefund;
use App\Domain\Loyalty\Listeners\StampOrders;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\MenuSchedule;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\Product;
use App\Domain\Messaging\Listeners\NotifyGuestAboutOrder;
use App\Domain\Messaging\Messenger;
use App\Domain\Notifications\Listeners\AlertOnFailedRefund;
use App\Domain\Notifications\Listeners\PushOrderReadyToGuest;
use App\Domain\Notifications\Push\PushTransport;
use App\Domain\Notifications\Push\VapidTransport;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Events\OrderUpdated;
use App\Domain\Ordering\KitchenLoad;
use App\Domain\Ordering\Listeners\BroadcastOrderChange;
use App\Domain\Ordering\Listeners\QueueScheduledRelease;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\Events\PaymentSucceeded;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Platform\Models\Announcement;
use App\Domain\Printing\Listeners\AutoPrintKitchenTicket;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Security\Events\PrincipalLocked;
use App\Domain\Security\Listeners\HandleSuccessfulSignIn;
use App\Domain\Security\Listeners\RaiseSecurityAlert;
use App\Domain\Security\Listeners\RecordFailedSignIn;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\SessionRecord;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Livewire\WorkspaceNotices;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\AnnouncementPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DevicePolicy;
use App\Policies\EmployeePolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LeadPolicy;
use App\Policies\MenuPolicy;
use App\Policies\PlanPolicy;
use App\Policies\PlatformRolePolicy;
use App\Policies\PositionPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\RestaurantPolicy;
use App\Policies\RewardPolicy;
use App\Policies\SecurityAlertPolicy;
use App\Policies\SessionRecordPolicy;
use App\Policies\StampProgramPolicy;
use App\Policies\UserPolicy;
use App\Support\Digits;
use Filament\Forms\Components\TextInput;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(CustomerAuth::class);
        $this->app->scoped(Messenger::class);
        $this->app->scoped(KitchenLoad::class);
        // Real providers are configured per restaurant in Phase 9; until then the Fake driver.
        $this->app->scoped(GatewayResolver::class);
        $this->app->bind(PushTransport::class, VapidTransport::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureSecurity();
        $this->configureAuthorization();
        $this->configurePolicies();
        $this->configureFilament();
    }

    private function configureModels(): void
    {
        // Lazy loading and silently dropped attributes are bugs: fail loudly outside production.
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    private function configureSecurity(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        // Public API: per key (hashed), after the key was checked; unknown callers per IP.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute((int) config('integrations.api.requests_per_minute', 120))
            ->by('api:'.($request->attributes->get('api_client')?->getKey() ?? 'ip:'.$request->ip()))
            ->response(fn (Request $request, array $headers) => response()->json(['error' => ['code' => 'rate_limited', 'message' => 'Too many requests. Slow down and retry after the Retry-After seconds.']], 429, $headers)));

        RateLimiter::for('leads', fn (Request $request): array => [
            Limit::perMinute(3)->by('leads:'.$request->ip()),
            Limit::perDay(20)->by('leads-day:'.$request->ip()),
        ]);

        Event::listen(Failed::class, RecordFailedSignIn::class);
        Event::listen(Login::class, HandleSuccessfulSignIn::class);
        Event::listen(PrincipalLocked::class, RaiseSecurityAlert::class);
        Event::listen(OrderStatusChanged::class, QueueScheduledRelease::class);
        Event::listen([OrderStatusChanged::class, OrderUpdated::class], BroadcastOrderChange::class);
        Event::listen(OrderStatusChanged::class, AutoPrintKitchenTicket::class);
        Event::listen(OrderStatusChanged::class, NotifyGuestAboutOrder::class);
        Event::listen(OrderStatusChanged::class, PushOrderReadyToGuest::class);
        Event::listen(PaymentRefunded::class, AlertOnFailedRefund::class);
        Event::listen([OrderStatusChanged::class, PaymentSucceeded::class, PaymentRefunded::class], SendWebhooks::class);
        Event::listen(OrderStatusChanged::class, SendAcceptedOrdersToPos::class);
        Event::listen(OrderStatusChanged::class, AwardPointsOnPickup::class);
        Event::listen(OrderStatusChanged::class, ReturnBenefitsOnCancel::class);
        Event::listen(PaymentRefunded::class, ReversePointsOnRefund::class);
        Event::listen(OrderStatusChanged::class, RefreshGuestStats::class);
        Event::listen(OrderStatusChanged::class, AttributeCampaignOrder::class);
        Event::listen([OrderStatusChanged::class, PaymentRefunded::class], StampOrders::class);
    }

    private function configureAuthorization(): void
    {
        // No Gate::before shortcut for the super admin: hasPlatformPermission() and
        // hasRestaurantPermission() already grant them every permission, while
        // policies can still forbid things nobody may do (deleting invoices,
        // editing one's own role...).
        foreach (PlatformPermission::cases() as $permission) {
            Gate::define($permission->ability(), fn (User $user): bool => $user->hasPlatformPermission($permission));
        }

        foreach (RestaurantPermission::cases() as $permission) {
            Gate::define($permission->ability(), function (User $user, ?Restaurant $restaurant = null) use ($permission): bool {
                $restaurant ??= app(TenantContext::class)->get();

                return $restaurant !== null && $user->hasRestaurantPermission($restaurant, $permission);
            });
        }
    }

    private function configurePolicies(): void
    {
        Gate::policy(Restaurant::class, RestaurantPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(WebhookEndpoint::class, IntegrationPolicy::class);
        Gate::policy(ApiClient::class, IntegrationPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Reward::class, RewardPolicy::class);
        Gate::policy(StampProgram::class, StampProgramPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Coupon::class, PromotionPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(PlatformRole::class, PlatformRolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(SecurityAlert::class, SecurityAlertPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(SessionRecord::class, SessionRecordPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);

        foreach ([Product::class, MenuCategory::class, ModifierGroup::class, MenuSchedule::class] as $menuModel) {
            Gate::policy($menuModel, MenuPolicy::class);
        }
    }

    private function configureFilament(): void
    {
        Livewire::component('workspace-notices', WorkspaceNotices::class);

        FilamentTimezone::set((string) config('app.display_timezone'));

        // Arabic keyboards type ٠-٩; store Western digits everywhere.
        TextInput::configureUsing(function (TextInput $input): void {
            $input->dehydrateStateUsing(fn (mixed $state, TextInput $component): mixed => is_string($state) && ! $component->isPassword()
                ? Digits::toLatin($state)
                : $state);
        });
    }
}
