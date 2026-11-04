<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Branches\Models\Branch;
use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\Models\QrScan;
use App\Domain\Crm\Consent;
use App\Domain\Crm\Segments;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerVehicle;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Points;
use App\Domain\Loyalty\Tiers;
use App\Domain\Menu\Models\Product;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Models\Order;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\ReferralSettings;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo guests for one restaurant, so the growth screens have something to
 * show: fourteen guests with months of picked-up orders (and their
 * segments), a sent WhatsApp campaign that brought orders back, a tracked
 * QR sticker with a month of scans, and two invites. Safe to run twice.
 * Run after DemoMenuSeeder and DemoGrowthSeeder.
 */
final class DemoCrmSeeder extends Seeder
{
    /** [phone, name, locale, plate, orders, first order (days ago), last order (days ago), offers consent] */
    private const GUESTS = [
        ['+97455552001', 'نورة الكواري', 'ar', '211874', 14, 150, 2, true],
        ['+97455552002', 'Ahmed Al-Thani', 'en', '305112', 9, 120, 4, true],
        ['+97455552003', 'فاطمة المري', 'ar', '418930', 6, 90, 6, true],
        ['+97455552004', 'Omar Haddad', 'en', '120558', 5, 80, 9, true],
        ['+97455552005', 'مريم السليطي', 'ar', '664201', 4, 70, 12, true],
        ['+97455552006', 'Yousef Karim', 'en', '782234', 3, 60, 20, false],
        ['+97455552007', 'عبدالله النعيمي', 'ar', '590117', 3, 100, 45, true],
        ['+97455552008', 'Layla Mansour', 'en', '431906', 2, 95, 52, true],
        ['+97455552009', 'حمد العطية', 'ar', '277045', 2, 140, 75, false],
        ['+97455552010', 'Sara Ibrahim', 'en', '359882', 1, 110, 110, true],
        ['+97455552011', 'خالد الهاجري', 'ar', '148273', 1, 18, 18, true],
        ['+97455552012', 'Rania Farouk', 'en', '506611', 1, 9, 9, false],
        ['+97455552013', 'جاسم الدوسري', 'ar', '693357', 1, 5, 5, true],
        ['+97455552014', 'Hessa Al-Sulaiti', 'en', null, 0, 0, 0, true],
    ];

    private const QR_SCANS = 260;

    private string $currency = 'QAR';

    /** Points per whole currency unit, when the restaurant runs points. */
    private int $earnRate = 0;

    public function run(Restaurant $restaurant): void
    {
        $branch = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->orderBy('id')->first();
        $products = Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('is_available', true)->orderBy('id')->limit(12)->get();

        if ($branch === null || $products->isEmpty()) {
            return;
        }

        $this->currency = $restaurant->currency;
        $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $restaurant->id)->first();
        $this->earnRate = $program !== null && $program->isActive() ? (int) $program->earn_points_per_unit : 0;
        $now = CarbonImmutable::now();
        $guests = $this->guests($restaurant, $branch, $products, $now);

        $this->campaign($restaurant, $guests, $branch, $products, $now);
        $this->qrSticker($restaurant, $branch, $guests, $products, $now);
        $this->referrals($restaurant, $guests, $now);

        foreach ($guests as $guest) {
            app(Tiers::class)->recompute($guest);
        }

        app(Segments::class)->compute($restaurant, $now);
    }

    /**
     * A real person's own guest account in a demo restaurant (their phone, so
     * sign-in codes and replies reach them): offers accepted, a car, and a
     * picked-up order at each of the given moments (hours ago).
     *
     * @param  list<float>  $hoursAgo
     */
    public function ownGuest(Restaurant $restaurant, string $phone, string $name, array $hoursAgo): Customer
    {
        $branch = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->orderBy('id')->firstOrFail();
        $products = Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('is_available', true)->orderBy('id')->limit(12)->get();
        $this->currency = $restaurant->currency;
        $program = LoyaltyProgram::withoutTenancy()->where('restaurant_id', $restaurant->id)->first();
        $this->earnRate = $program !== null && $program->isActive() ? (int) $program->earn_points_per_unit : 0;

        $guest = Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('phone', $phone)->first()
            ?? Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => $phone, 'name' => $name, 'locale' => 'ar']);

        if (! CustomerVehicle::withoutTenancy()->where('customer_id', $guest->id)->exists()) {
            CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'plate' => '777001', 'color' => 'White', 'make' => 'Lexus', 'is_default' => true]);
        }

        app(Consent::class)->give($guest, 'account');
        $now = CarbonImmutable::now();

        foreach (array_values($hoursAgo) as $i => $hours) {
            $this->order($guest, $branch, $products->slice($i % max(1, $products->count()), 2)->values(), $now->subMinutes((int) round($hours * 60)));
        }

        app(Tiers::class)->recompute($guest);

        return $guest;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, Customer> by phone
     */
    private function guests(Restaurant $restaurant, Branch $branch, Collection $products, CarbonImmutable $now): array
    {
        $guests = [];

        foreach (self::GUESTS as $index => [$phone, $name, $locale, $plate, $orders, $firstDaysAgo, $lastDaysAgo, $consents]) {
            $guest = Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('phone', $phone)->first();

            if ($guest === null) {
                $guest = Customer::withoutTenancy()->forceCreate([
                    'restaurant_id' => $restaurant->id,
                    'phone' => $phone,
                    'name' => $name,
                    'locale' => $locale,
                    'created_at' => $now->subDays(max($firstDaysAgo, 1) + 1),
                ]);

                if ($plate !== null) {
                    CustomerVehicle::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $guest->id, 'plate' => $plate, 'color' => ['White', 'Black', 'Silver', 'Red'][$index % 4], 'make' => ['Toyota', 'Nissan', 'Lexus', 'GMC'][$index % 4], 'is_default' => true]);
                }

                // Spread the orders between the first and the last visit.
                for ($i = 0; $i < $orders; $i++) {
                    $daysAgo = $orders === 1 ? $firstDaysAgo : (int) round($firstDaysAgo - ($firstDaysAgo - $lastDaysAgo) * $i / ($orders - 1));
                    $this->order($guest, $branch, $products->slice(($index + $i) % $products->count(), 2)->values(), $now->subDays($daysAgo)->setTime(12 + ($i % 8), 15 * ($i % 4)));
                }

                if ($consents) {
                    app(Consent::class)->give($guest, $index % 3 === 0 ? 'account' : 'checkout');
                }
            }

            $guests[$phone] = $guest;
        }

        return $guests;
    }

    /**
     * A WhatsApp offer sent six days ago to regulars who agreed to offers;
     * three of them came back through its link.
     *
     * @param  array<string, Customer>  $guests
     * @param  Collection<int, Product>  $products
     */
    private function campaign(Restaurant $restaurant, array $guests, Branch $branch, Collection $products, CarbonImmutable $now): void
    {
        if (Campaign::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name', 'Weekend karak offer')->exists()) {
            return;
        }

        $reward = Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', '10 QAR off')->first();
        $sentAt = $now->subDays(6)->setTime(17, 30);

        $campaign = Campaign::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Weekend karak offer',
            'segments' => ['regular', 'at_risk'],
            'channel' => CampaignChannel::WhatsApp,
            'message' => [
                'en' => 'This weekend only: 10 QAR off your karak and breakfast. Order from your car in a minute.',
                'ar' => 'لهذه العطلة فقط: خصم 10 ر.ق على الكرك والفطور. اطلب من سيارتك في دقيقة.',
            ],
            'reward_id' => $reward?->id,
        ]);

        $recipients = collect($guests)->filter(fn (Customer $guest): bool => $guest->fresh()?->acceptsOffers() ?? false)->take(9)->values();
        $orders = 0;
        $revenue = 0;

        foreach ($recipients as $i => $guest) {
            $message = new CampaignMessage(['restaurant_id' => $restaurant->id, 'campaign_id' => $campaign->id, 'customer_id' => $guest->id, 'locale' => $guest->locale ?? 'ar']);
            $message->forceFill(['status' => CampaignMessage::SENT, 'channel' => 'whatsapp', 'sent_at' => $sentAt->addSeconds($i * 4)])->save();

            // WhatsApp's receipts: all but one delivered, most of them read.
            $delivery = MessageDelivery::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'channel' => 'whatsapp',
                'purpose' => Message::CAMPAIGN,
                'to_hash' => hash('sha256', $guest->phone),
                'to_masked' => substr($guest->phone, 0, 4).'••••'.substr($guest->phone, -2),
                'status' => match (true) {
                    $i === $recipients->count() - 1 => MessageDelivery::SENT,
                    $i % 4 === 3 => MessageDelivery::DELIVERED,
                    default => MessageDelivery::READ,
                },
                'subject_type' => $message->getMorphClass(),
                'subject_id' => $message->id,
            ]);
            $message->forceFill(['message_delivery_id' => $delivery->id])->save();

            if ($i < 3) {
                $order = $this->order($guest, $branch, $products->slice($i, 2)->values(), $sentAt->addDays($i + 1)->setTime(19, 10), ['source' => 'campaign', 'campaign' => $campaign->link_code, 'channel' => 'whatsapp']);
                $message->forceFill(['order_id' => $order->id, 'revenue_minor' => $order->total_minor, 'attributed_at' => $order->picked_up_at])->save();
                $orders++;
                $revenue += $order->total_minor;
            }
        }

        $campaign->forceFill([
            'status' => 'sent',
            'started_at' => $sentAt,
            'finished_at' => $sentAt->addMinutes(2),
            'recipients_count' => $recipients->count(),
            'sent_count' => $recipients->count(),
            'orders_count' => $orders,
            'revenue_minor' => $revenue,
        ])->save();
    }

    /**
     * A car-window sticker at the first branch: a month of scans, about two
     * in five reaching the menu, and a handful of orders.
     *
     * @param  array<string, Customer>  $guests
     * @param  Collection<int, Product>  $products
     */
    private function qrSticker(Restaurant $restaurant, Branch $branch, array $guests, Collection $products, CarbonImmutable $now): void
    {
        if (QrCampaign::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name', 'Car-window sticker')->exists()) {
            return;
        }

        $code = QrCampaign::withoutTenancy()->forceCreate([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'name' => 'Car-window sticker',
            'placement' => 'Car-window sticker',
            'lane' => '1',
            'created_at' => $now->subDays(32),
        ]);

        mt_srand($restaurant->id);

        for ($i = 0; $i < self::QR_SCANS; $i++) {
            // Busier towards today, and in the evening.
            $daysAgo = (int) floor(30 * (mt_rand(0, 1000) / 1000) ** 1.6);
            $at = $now->subDays($daysAgo)->setTime(mt_rand(0, 9) < 7 ? mt_rand(17, 22) : mt_rand(10, 16), mt_rand(0, 59));

            if ($at->gt($now)) {
                $at = $now->subMinutes(mt_rand(5, 300));
            }

            $scan = new QrScan;
            $scan->forceFill([
                'restaurant_id' => $restaurant->id,
                'qr_campaign_id' => $code->id,
                'device_hash' => hash('sha256', 'demo-device-'.$i),
                'is_bot' => $i % 23 === 0,
                'scanned_at' => $at,
                'visited_at' => $i % 5 < 2 ? $at->addSeconds(mt_rand(3, 40)) : null,
            ])->save();
        }

        foreach (array_slice(array_values($guests), 0, 7) as $i => $guest) {
            $this->order($guest, $branch, $products->slice($i + 1, 1)->values(), $now->subDays(2 + $i * 4)->setTime(20, 5), ['source' => 'qr', 'campaign' => $code->code, 'channel' => 'Car-window sticker', 'lane' => '1']);
        }
    }

    /**
     * Invites on (a free drink for the inviter, 10 QAR off for the friend):
     * one friend rewarded after their first order, one still to order.
     *
     * @param  array<string, Customer>  $guests
     */
    private function referrals(Restaurant $restaurant, array $guests, CarbonImmutable $now): void
    {
        $mine = Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', 'Free drink')->first()
            ?? Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', '20% off your order')->first();
        $theirs = Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', '10 QAR off')->first();

        if ($mine === null || $theirs === null) {
            return;
        }

        if (! ReferralSettings::for($restaurant)->enabled) {
            ReferralSettings::save($restaurant, ['enabled' => true, 'referrer_reward_id' => $mine->id, 'friend_reward_id' => $theirs->id, 'monthly_cap' => ReferralSettings::DEFAULT_CAP]);
        }

        $inviter = $guests['+97455552001'];
        $rewarded = $guests['+97455552013'];
        $waiting = $guests['+97455552014'];

        if (Referral::withoutTenancy()->whereIn('friend_id', [$rewarded->id, $waiting->id])->exists()) {
            return;
        }

        $order = Order::withoutTenancy()->where('customer_id', $rewarded->id)->oldest('id')->first();
        $grant = app(GrantReward::class);

        Referral::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'referrer_id' => $inviter->id,
            'friend_id' => $rewarded->id,
            'status' => Referral::REWARDED,
            'order_id' => $order?->id,
            'referrer_reward_id' => $grant->handle($inviter, $mine, 'referral', null)->id,
            'friend_reward_id' => $grant->handle($rewarded, $theirs, 'referral', null)->id,
            'joined_at' => $now->subDays(6),
            'decided_at' => $order->picked_up_at ?? $now->subDays(5),
        ]);

        Referral::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'referrer_id' => $inviter->id,
            'friend_id' => $waiting->id,
            'status' => Referral::PENDING,
            'joined_at' => $now->subDay(),
        ]);
    }

    /**
     * A picked-up order written straight to the database (demo history, so
     * no events, so no messages go out). Points are earned as at pickup.
     *
     * @param  Collection<int, Product>  $items
     * @param  array<string, string>|null  $source
     */
    private function order(Customer $guest, Branch $branch, Collection $items, CarbonImmutable $at, ?array $source = null): Order
    {
        $items = $items->isEmpty() ? Product::withoutTenancy()->where('restaurant_id', $guest->restaurant_id)->limit(1)->get() : $items;
        $total = (int) $items->sum('price_minor');
        $vehicle = CustomerVehicle::withoutTenancy()->where('customer_id', $guest->id)->first();

        $order = Order::withoutTenancy()->forceCreate([
            'restaurant_id' => $guest->restaurant_id,
            'branch_id' => $branch->id,
            'customer_id' => $guest->id,
            'number' => (int) Order::withoutTenancy()->where('restaurant_id', $guest->restaurant_id)->max('number') + 1,
            'short_code' => Str::upper(Str::random(8)),
            'pickup_code' => (string) random_int(100000, 999999),
            'status' => 'picked_up',
            'payment_status' => 'paid',
            'payment_method' => 'pay_at_pickup',
            'currency' => $this->currency,
            'subtotal_minor' => $total,
            'total_minor' => $total,
            'prep_minutes' => 8,
            'vehicle' => $vehicle?->snapshot(),
            'source' => $source,
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => $at,
            'picked_up_at' => $at->addMinutes(9),
            'created_at' => $at,
            'updated_at' => $at->addMinutes(9),
        ]);

        if ($this->earnRate > 0 && $total >= 100) {
            $points = intdiv($total, 100) * $this->earnRate;
            // Dated like the order, so the guest's history and expiry read true.
            $previous = Carbon::getTestNow();
            Carbon::setTestNow($order->picked_up_at);
            app(Points::class)->credit($guest, LedgerType::Earn, $points, $order, null);
            Carbon::setTestNow($previous);
            $order->forceFill(['points_earned' => $points])->save();
        }

        foreach ($items as $product) {
            $order->items()->create([
                'restaurant_id' => $guest->restaurant_id,
                'product_id' => $product->id,
                'name' => $product->getTranslations('name'),
                'quantity' => 1,
                'unit_price_minor' => $product->price_minor,
                'line_total_minor' => $product->price_minor,
            ]);
        }

        return $order;
    }
}
