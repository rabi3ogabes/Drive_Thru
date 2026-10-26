<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Crm\Consent;
use App\Domain\Customers\CustomerAuth;
use App\Domain\Customers\Enums\VehicleType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Livewire\Customer\Concerns\SignsInWithPhone;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The guest's own details here: name, birthday and language, their cars,
 * signed-in devices (with sign out elsewhere), favourites, and sign out.
 */
class Account extends Component
{
    use GuestPage;
    use SignsInWithPhone;

    public string $name = '';

    public string $birthday = '';

    public string $locale = 'ar';

    public bool $orderUpdates = true;

    public bool $receipts = true;

    public bool $offers = false;

    public bool $addingVehicle = false;

    public string $plate = '';

    public string $color = '';

    public string $make = '';

    public string $vehicleType = 'car';

    public function mount(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
        $this->prefill($this->customer());
    }

    public function saveProfile(): void
    {
        $customer = $this->signedInCustomer();

        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'birthday' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'locale' => ['required', Rule::in(Locales::codes())],
        ]);

        $customer->forceFill([
            'name' => trim($data['name']),
            'birthday' => $data['birthday'] ?: null,
            'locale' => $data['locale'],
            'notification_prefs' => array_merge((array) $customer->notification_prefs, ['order_updates' => $this->orderUpdates, 'receipts' => $this->receipts]),
        ])->save();

        if ($this->offers && ! $customer->acceptsOffers()) {
            app(Consent::class)->give($customer, 'account');
        } elseif (! $this->offers && $customer->acceptsOffers()) {
            app(Consent::class)->withdraw($customer, 'account');
        }

        $this->toast(__('guest.account.saved'));
    }

    public function addVehicle(): void
    {
        $customer = $this->signedInCustomer();

        $this->validate([
            'plate' => ['required', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:30'],
            'make' => ['nullable', 'string', 'max:40'],
            'vehicleType' => ['required', Rule::enum(VehicleType::class)],
        ]);

        if ($customer->vehicles()->count() >= 10) {
            $this->addError('plate', __('guest.account.too_many_cars'));

            return;
        }

        $customer->vehicles()->create([
            'restaurant_id' => $customer->restaurant_id,
            'plate' => mb_strtoupper(trim($this->plate)),
            'color' => trim($this->color) ?: null,
            'make' => trim($this->make) ?: null,
            'type' => $this->vehicleType,
            'is_default' => ! $customer->vehicles()->exists(),
        ]);

        $this->reset('plate', 'color', 'make', 'vehicleType', 'addingVehicle');
        $this->toast(__('guest.account.car_added'));
    }

    public function makeDefault(int $vehicleId): void
    {
        $customer = $this->signedInCustomer();
        $vehicle = $customer->vehicles()->findOrFail($vehicleId);

        DB::transaction(function () use ($customer, $vehicle): void {
            $customer->vehicles()->update(['is_default' => false]);
            $vehicle->forceFill(['is_default' => true])->save();
        });
    }

    public function deleteVehicle(int $vehicleId): void
    {
        $customer = $this->signedInCustomer();
        $vehicle = $customer->vehicles()->findOrFail($vehicleId);
        $wasDefault = $vehicle->is_default;
        $vehicle->delete();

        if ($wasDefault) {
            $customer->vehicles()->latest('id')->first()?->forceFill(['is_default' => true])->save();
        }

        $this->toast(__('guest.account.car_removed'));
    }

    public function revokeDevice(int $sessionId): void
    {
        $customer = $this->signedInCustomer();
        $session = $customer->sessions()->whereNull('revoked_at')->findOrFail($sessionId);

        if ($session->token_hash === app(CustomerAuth::class)->currentSessionHash($this->restaurant)) {
            $this->signOut();

            return;
        }

        $session->forceFill(['revoked_at' => now()])->save();
        $this->toast(__('guest.account.device_signed_out'));
    }

    public function removeFavorite(int $productId): void
    {
        $this->signedInCustomer()->favorites()->detach($productId);
    }

    public function signOut(): void
    {
        app(CustomerAuth::class)->logout($this->restaurant);
        $this->redirectRoute('public.restaurant', $this->restaurant, navigate: true);
    }

    public function render(): View
    {
        $customer = $this->customer();
        $current = app(CustomerAuth::class)->currentSessionHash($this->restaurant);

        return $this->page('livewire.customer.account', [
            'customer' => $customer,
            'vehicles' => $customer?->vehicles()->orderByDesc('is_default')->latest('id')->get() ?? collect(),
            'devices' => $customer?->sessions()->whereNull('revoked_at')->latest('last_active_at')->limit(10)->get() ?? collect(),
            'current' => $current,
            'favorites' => $customer?->favorites()->with('category')->limit(30)->get() ?? collect(),
            'locales' => Locales::codes(),
            'rewardsOn' => $this->restaurant->hasFeature(PlanFeature::Loyalty)
                && (LoyaltyProgram::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->first()?->isActive() ?? false),
        ], __('guest.nav.account'));
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('guest.checkout.name'),
            'plate' => __('guest.checkout.plate'),
            'color' => __('guest.checkout.color'),
            'make' => __('guest.checkout.make'),
            'scheduledAt' => __('guest.timing.scheduled'),
            'notes' => __('guest.checkout.notes'),
            'birthday' => __('guest.account.birthday'),
            'locale' => __('guest.account.language'),
        ];
    }

    protected function signedIn(Customer $customer): void
    {
        $this->prefill($customer);
    }

    private function signedInCustomer(): Customer
    {
        $customer = $this->customer();
        abort_if($customer === null, 403);

        return $customer;
    }

    private function prefill(?Customer $customer): void
    {
        if ($customer === null) {
            return;
        }

        $this->name = (string) $customer->name;
        $this->birthday = $customer->birthday?->format('Y-m-d') ?? '';
        $this->locale = $customer->locale ?? app()->getLocale();
        $this->orderUpdates = $customer->wants('order_updates');
        $this->receipts = $customer->wants('receipts');
        $this->offers = $customer->acceptsOffers();
    }
}
