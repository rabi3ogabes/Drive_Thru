<?php

declare(strict_types=1);

namespace App\Livewire\Customer;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Branches\BranchSchedule;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Lane;
use App\Domain\Customers\Enums\VehicleType;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Loyalty\PointsRedemption;
use App\Domain\Menu\BranchMenu as Menu;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Capacity;
use App\Domain\Ordering\CartPricer;
use App\Domain\Ordering\Data\Adjustment;
use App\Domain\Ordering\Data\CheckoutBenefits;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Data\PricedCart;
use App\Domain\Ordering\Enums\OrderTiming;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Exceptions\OrderRejected;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\Concerns\GuestPage;
use App\Livewire\Customer\Concerns\SignsInWithPhone;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * One calm page from bag to order: review the bag, sign in with a code
 * (only the first time), pick the car, when to start cooking and how to
 * pay. Totals shown here are the server's; PlaceOrder re-prices and is
 * idempotent, so a double tap never creates two orders.
 */
class Checkout extends Component
{
    use GuestPage;
    use SignsInWithPhone;

    public const DELAYS = [10, 15, 20, 30, 45, 60];

    #[Locked]
    public int $branchId;

    /** One key per checkout visit: retries and double taps reuse it. */
    #[Locked]
    public string $idempotencyKey;

    public string $name = '';

    public ?int $vehicleId = null;

    public bool $addingVehicle = false;

    public string $plate = '';

    public string $color = '';

    public string $make = '';

    public string $vehicleType = 'car';

    public ?int $laneId = null;

    public string $timing = 'now';

    public int $delay = 15;

    public string $scheduledAt = '';

    public string $payment = 'card';

    public string $notes = '';

    #[Locked]
    public ?string $problem = null;

    /** The code field; an applied code moves to $couponCode once the server accepts it. */
    public string $couponInput = '';

    #[Locked]
    public ?string $couponCode = null;

    #[Locked]
    public ?string $couponError = null;

    #[Locked]
    public ?int $walletRewardId = null;

    #[Locked]
    public bool $usePoints = false;

    /** The total on screen when the guest taps "Place order". */
    #[Locked]
    public ?int $shownTotal = null;

    /** "HH:MM" local, when the kitchen was full and suggested another time. */
    #[Locked]
    public ?string $suggestedAt = null;

    public function mount(Restaurant $restaurant, string $branch): void
    {
        $this->restaurant = $restaurant;
        $model = $this->findBranch($branch);

        if ($model === null) {
            abort(response()->view('public.branch-missing', ['restaurant' => $restaurant], 404));
        }

        $this->branchId = $model->id;
        $this->idempotencyKey = (string) Str::uuid();
        $this->rememberSource();
        $this->prefill($this->customer());

        if (! in_array(PaymentMethod::tryFrom($this->payment), $this->methods(), true)) {
            $this->payment = $this->methods()[0]->value;
        }

        // Closed right now: the only way to order is for later.
        if (! app(BranchSchedule::class)->state($model)->acceptsOrders()) {
            $this->timing = OrderTiming::Scheduled->value;
            $opens = app(BranchSchedule::class)->reopensAt($model);
            $this->scheduledAt = $opens === null ? '' : $opens->setTimezone($restaurant->timezone)->addMinutes(15)->format('Y-m-d\TH:i');
        }

        $lane = $this->source()['lane'] ?? null;
        if ($lane !== null && ctype_digit($lane)) {
            $this->laneId = $this->lanes()->firstWhere('id', (int) $lane)?->id;
        }
    }

    public function branch(): Branch
    {
        return once(fn (): Branch => Branch::withoutTenancy()->with(['restaurant', 'hours', 'closures', 'lanes'])->findOrFail($this->branchId));
    }

    public function setQuantity(string $key, int $quantity): void
    {
        $this->cart()->setQuantity($this->restaurant->id, $key, max(0, min(CartPricer::MAX_QUANTITY, $quantity)));
        $this->problem = null;
    }

    public function remove(string $key): void
    {
        $this->cart()->remove($this->restaurant->id, $key);
        $this->problem = null;
    }

    /** Adds a suggested item that needs no choices; otherwise opens it on the menu. */
    public function addSuggestion(int $productId): void
    {
        $product = Product::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->with('modifierGroups')->find($productId);

        if ($product === null) {
            return;
        }

        $needsChoices = $product->isCombo() || $product->modifierGroups->contains(fn ($g): bool => $g->min_select > 0);

        if ($needsChoices) {
            $this->redirect(route('public.branch', [$this->restaurant, $this->branch()->code]).'?item='.$product->id, navigate: true);

            return;
        }

        $priced = app(CartPricer::class)->price($this->branch(), [['key' => 'check', 'product_id' => $product->id, 'quantity' => 1, 'options' => [], 'combo' => [], 'notes' => null]]);

        if (! $priced->lines[0]->isValid()) {
            $this->toast($priced->lines[0]->error ?? '', 'error');

            return;
        }

        $this->cart()->add($this->restaurant->id, $product->id);
        $this->toast(__('guest.menu.added', ['item' => $product->name]));
    }

    public function useSuggestedTime(): void
    {
        if ($this->suggestedAt === null) {
            return;
        }

        $this->timing = OrderTiming::Scheduled->value;
        $this->scheduledAt = $this->suggestedAt;
        $this->problem = null;
        $this->suggestedAt = null;
    }

    public function applyCoupon(): void
    {
        $code = Coupon::normalize($this->couponInput);
        $this->couponError = null;

        if ($code === '') {
            return;
        }

        $lines = $this->cart()->lines($this->restaurant->id);
        $bag = $lines === [] ? null : app(CartPricer::class)->price($this->branch(), $lines, null, new CheckoutBenefits($this->customer(), $code));
        $applied = $bag !== null && in_array(Adjustment::COUPON, array_map(fn (Adjustment $a): string => $a->kind, $bag->adjustments), true);

        if (! $applied) {
            $this->couponError = $bag->benefitError ?? __('promotions.coupon_errors.not_applicable');

            return;
        }

        $this->couponCode = $code;
        $this->couponInput = '';
    }

    public function removeCoupon(): void
    {
        $this->couponCode = null;
        $this->couponError = null;
    }

    public function chooseReward(?int $walletRewardId): void
    {
        $customer = $this->customer();

        $this->walletRewardId = $walletRewardId !== null && $customer !== null
            && WalletReward::withoutTenancy()->usable()->where('customer_id', $customer->id)->whereKey($walletRewardId)->exists()
            ? $walletRewardId
            : null;
    }

    public function togglePoints(): void
    {
        $this->usePoints = ! $this->usePoints && $this->customer() !== null;
    }

    public function place(): void
    {
        $this->problem = null;
        $this->suggestedAt = null;
        $customer = $this->customer();

        if ($customer === null) {
            $this->problem = __('guest.checkout.sign_in_first');

            return;
        }

        $this->validate($this->rules($customer));

        if (($customer->name ?? '') === '' || $customer->name !== trim($this->name)) {
            $customer->forceFill(['name' => trim($this->name)])->save();
        }

        $vehicle = $this->resolveVehicle($customer);
        $branch = $this->branch();
        $timing = OrderTiming::from($this->timing);

        $priced = app(CartPricer::class)->price($branch, $this->cart()->lines($this->restaurant->id), null, $this->benefits($customer));

        try {
            $order = app(PlaceOrder::class)->handle(new OrderRequest(
                customer: $customer,
                branch: $branch,
                lines: $this->cart()->lines($this->restaurant->id),
                paymentMethod: PaymentMethod::from($this->payment),
                idempotencyKey: $this->idempotencyKey,
                timing: $timing,
                delayMinutes: $timing === OrderTiming::Delay ? $this->delay : null,
                scheduledFor: $timing === OrderTiming::Scheduled ? $this->scheduledFor() : null,
                vehicleId: $vehicle?->id,
                laneId: $this->laneId,
                notes: trim($this->notes) === '' ? null : $this->notes,
                source: $this->source(),
                locale: app()->getLocale(),
                benefits: new CheckoutBenefits($customer, $this->couponCode, $this->walletRewardId, $priced->pointsRedeemed),
                expectedTotalMinor: $this->shownTotal,
            ));
        } catch (OrderRejected $rejected) {
            $this->problem = $rejected->getMessage();
            $slot = $rejected->nextSlot === null ? null : $this->schedulableSlot($branch, $rejected->nextSlot);

            if ($slot !== null) {
                $local = $slot->setTimezone($this->restaurant->timezone);
                $this->suggestedAt = $local->format('Y-m-d\TH:i');
                $this->problem = __('ordering.errors.capacity', ['time' => $local->format('H:i')]);
            }

            return;
        }

        $this->cart()->clear($this->restaurant->id);
        session()->forget("source.{$this->restaurant->id}");
        $orderUrl = route('customer.order', [$this->restaurant, $order]);

        if ($order->payment_method->isOnline() && $order->status->value === 'pending_payment') {
            try {
                $redirect = app(StartPayment::class)->handle($order, $order->payment_method);
            } catch (Throwable $e) {
                report($e);
                $redirect = null;
            }

            if ($redirect !== null) {
                $this->redirect($redirect);

                return;
            }
        }

        $this->redirect($orderUrl.'?placed=1', navigate: true);
    }

    public function render(): View
    {
        $branch = $this->branch();
        $customer = $this->customer();
        $lines = $this->cart()->lines($this->restaurant->id);
        $bag = $lines === [] ? null : app(CartPricer::class)->price($branch, $lines, null, $this->benefits($customer));
        $savings = $this->savings($branch, $customer, $bag);
        $this->shownTotal = $bag?->totalMinor;
        $schedule = app(BranchSchedule::class);
        $tz = $this->restaurant->timezone;

        return $this->page('livewire.customer.checkout', [
            'branch' => $branch,
            'customer' => $customer,
            'bag' => $bag,
            'state' => $schedule->state($branch),
            'opensAt' => $schedule->reopensAt($branch)?->setTimezone($tz),
            'vehicles' => $customer?->vehicles()->orderByDesc('is_default')->latest('id')->get() ?? collect(),
            'lanes' => $this->lanes(),
            'suggestions' => $bag === null ? [] : $this->suggestions($branch, $bag),
            'methods' => $this->methods(),
            'delays' => self::DELAYS,
            'minSchedule' => now($tz)->addMinutes(20)->format('Y-m-d\TH:i'),
            'maxSchedule' => now($tz)->addDays(PlaceOrder::MAX_SCHEDULE_DAYS)->format('Y-m-d\TH:i'),
            'readyBy' => $bag === null ? null : now($tz)->addMinutes($bag->prepMinutes),
            'savings' => $savings,
        ], __('guest.checkout.title'));
    }

    private function benefits(?Customer $customer): CheckoutBenefits
    {
        return new CheckoutBenefits($customer, $this->couponCode, $this->walletRewardId, $this->usePoints ? (int) $customer?->points_balance : 0);
    }

    /**
     * What the guest can use here: codes (with Promotions), wallet rewards and
     * points (with an active loyalty program), and what points would take off.
     *
     * @return array{codes: bool, loyalty: bool, wallet: Collection<int, WalletReward>, balance: int, pointsOffer: array{points: int, discountMinor: int}}
     */
    private function savings(Branch $branch, ?Customer $customer, ?PricedCart $bag): array
    {
        $restaurant = $branch->restaurant;
        $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $restaurant->id)->first();
        $loyalty = $customer !== null && $restaurant->hasFeature(PlanFeature::Loyalty) && $program !== null && $program->isActive();
        $beforePoints = $bag === null ? 0 : $bag->subtotalMinor - array_sum(array_map(fn (Adjustment $a): int => $a->kind === Adjustment::POINTS ? 0 : $a->amountMinor, $bag->adjustments));

        return [
            'codes' => $restaurant->hasFeature(PlanFeature::Promotions),
            'loyalty' => $loyalty,
            'wallet' => $loyalty ? WalletReward::withoutTenancy()->usable()->with('reward')->where('customer_id', $customer?->id)->orderBy('expires_at')->get() : collect(),
            'balance' => (int) $customer?->points_balance,
            'pointsOffer' => $loyalty && $program !== null && $customer !== null
                ? app(PointsRedemption::class)->clamp($program, $customer, $customer->points_balance, $beforePoints)
                : ['points' => 0, 'discountMinor' => 0],
        ];
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
        $this->toast(__('guest.sign_in.welcome', ['name' => $customer->firstName() ?? '']));
    }

    /**
     * What this restaurant accepts: its provider's online methods, then pay at the window.
     *
     * @return list<PaymentMethod>
     */
    private function methods(): array
    {
        return app(GatewayResolver::class)->methods($this->restaurant);
    }

    private function prefill(?Customer $customer): void
    {
        if ($customer === null) {
            return;
        }

        $this->name = $this->name !== '' ? $this->name : (string) $customer->name;
        $vehicle = $customer->vehicles()->orderByDesc('is_default')->latest('id')->first();
        $this->vehicleId = $vehicle?->id;
        $this->addingVehicle = $vehicle === null;

        $last = $customer->orders()->latest('id')->value('payment_method');
        if ($last !== null) {
            $last = $last instanceof PaymentMethod ? $last : PaymentMethod::tryFrom((string) $last);
            $this->payment = $last !== null && in_array($last, $this->methods(), true) ? $last->value : $this->methods()[0]->value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Customer $customer): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'timing' => ['required', Rule::enum(OrderTiming::class)],
            'payment' => ['required', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, $this->methods()))],
            'notes' => ['nullable', 'string', 'max:300'],
            'delay' => ['integer', Rule::in(self::DELAYS)],
            'laneId' => ['nullable', 'integer'],
        ];

        if ($this->timing === OrderTiming::Scheduled->value) {
            $rules['scheduledAt'] = ['required', 'date_format:Y-m-d\TH:i'];
        }

        if ($this->addingVehicle) {
            $rules += [
                'plate' => ['required', 'string', 'max:20'],
                'color' => ['nullable', 'string', 'max:30'],
                'make' => ['nullable', 'string', 'max:40'],
                'vehicleType' => ['required', Rule::enum(VehicleType::class)],
            ];
        } else {
            $rules['vehicleId'] = ['required', 'integer', Rule::exists('customer_vehicles', 'id')->where('customer_id', $customer->id)];
        }

        return $rules;
    }

    private function resolveVehicle(Customer $customer): ?CustomerVehicle
    {
        if (! $this->addingVehicle) {
            return $customer->vehicles()->find($this->vehicleId);
        }

        $vehicle = $customer->vehicles()->create([
            'restaurant_id' => $customer->restaurant_id,
            'plate' => mb_strtoupper(trim($this->plate)),
            'color' => trim($this->color) ?: null,
            'make' => trim($this->make) ?: null,
            'type' => $this->vehicleType,
            'is_default' => ! $customer->vehicles()->exists(),
        ]);

        $this->vehicleId = $vehicle->id;
        $this->addingVehicle = false;
        $this->reset('plate', 'color', 'make');

        return $vehicle;
    }

    /**
     * The kitchen's next free slot, moved forward if needed so it is a time
     * a scheduled order accepts (at least 15 minutes from now).
     */
    private function schedulableSlot(Branch $branch, CarbonImmutable $slot): ?CarbonImmutable
    {
        $earliest = CarbonImmutable::now()->addMinutes(16);

        if ($slot->greaterThanOrEqualTo($earliest)) {
            return $slot;
        }

        $next = app(Capacity::class)->nextAvailable($branch, $slot);

        return $next !== null && $next->lessThan($earliest) ? app(Capacity::class)->nextAvailable($branch, $next) : $next;
    }

    private function scheduledFor(): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->scheduledAt, $this->restaurant->timezone) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, Lane>
     */
    private function lanes(): Collection
    {
        return $this->branch()->lanes->where('is_active', true)->sortBy('sort')->values();
    }

    /**
     * Up to three add-ons recommended by what is already in the bag.
     *
     * @return list<array{id: int, name: string, price: string, image: ?string}>
     */
    private function suggestions(Branch $branch, PricedCart $bag): array
    {
        $inBag = array_values(array_filter(array_map(fn ($line): ?int => $line->product?->id, $bag->lines)));

        if ($inBag === []) {
            return [];
        }

        $menu = app(Menu::class);
        $settings = $menu->settingsFor($branch);

        return array_values(Product::withoutTenancy()
            ->where('restaurant_id', $this->restaurant->id)
            ->whereIn('id', fn ($query) => $query->select('recommended_product_id')->from('product_recommendations')->whereIn('product_id', $inBag))
            ->whereNotIn('id', $inBag)
            ->with(['category.schedule', 'schedule'])
            ->limit(8)
            ->get()
            ->filter(fn (Product $p): bool => $menu->offer($p, $branch, $settings->get($p->id))->isAvailable())
            ->take(3)
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => Money::format($menu->offer($p, $branch, $settings->get($p->id))->priceMinor, $this->restaurant->currency),
                'image' => $p->imageUrl(400),
            ])
            ->all());
    }
}
