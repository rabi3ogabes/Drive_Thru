<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Customers\Models\Customer;
use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Feedback\RatingTags;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Badges\Badge;
use App\Domain\Loyalty\Badges\BadgeProgress;
use App\Domain\Loyalty\Badges\BadgeSettings;
use App\Domain\Loyalty\Badges\CustomerBadge;
use App\Domain\Loyalty\Birthdays\BirthdaySettings;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Support\Enums\TicketCategory;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo feedback for one restaurant, after DemoCrmSeeder: ratings on the
 * last month's picked-up orders (mostly happy, two unhappy with comments),
 * three support tickets (open, answered, resolved with a reward), the
 * birthday gift switched on with two birthdays this week, and badges
 * switched on with what the demo guests have earned. Safe to run twice.
 */
final class DemoFeedbackSeeder extends Seeder
{
    /** Up to this many ratings, newest orders first. */
    private const RATINGS = 40;

    /** [stars, tags, comment] cycled over the orders; two unhappy ones. */
    private const SAMPLES = [
        [5, ['fast', 'hot_fresh'], 'The karak was perfect, as always.'],
        [5, ['friendly'], null],
        [4, ['fast'], null],
        [5, ['accurate', 'fast'], null],
        [4, ['hot_fresh'], 'Fries could be a little crispier.'],
        [5, [], null],
        [2, ['cold', 'slow'], 'Waited 15 minutes and the burger was cold.'],
        [5, ['friendly', 'accurate'], 'Lovely team at the window.'],
        [4, [], null],
        [3, ['slow'], null],
        [5, ['fast'], null],
        [1, ['missing_item'], 'No fries in the bag.'],
    ];

    public function run(Restaurant $restaurant): void
    {
        $now = CarbonImmutable::now();

        $this->ratings($restaurant, $now);
        $this->tickets($restaurant, $now);
        $this->birthdays($restaurant, $now);
        $this->badges($restaurant, $now);
    }

    private function ratings(Restaurant $restaurant, CarbonImmutable $now): void
    {
        $orders = Order::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', OrderStatus::PickedUp->value)
            ->whereNotNull('customer_id')
            ->where('picked_up_at', '>=', $now->subDays(30))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('order_ratings')->whereColumn('order_ratings.order_id', 'orders.id'))
            ->latest('picked_up_at')
            ->limit(self::RATINGS - OrderRating::withoutTenancy()->where('restaurant_id', $restaurant->id)->count())
            ->get();

        foreach ($orders->values() as $i => $order) {
            [$stars, $tags, $comment] = self::SAMPLES[$i % count(self::SAMPLES)];
            $at = ($order->picked_up_at ?? $now)->addMinutes(35 + $i % 50);

            OrderRating::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'customer_id' => (int) $order->customer_id,
                'stars' => $stars,
                'tags' => array_values(array_intersect(RatingTags::for($stars), $tags)),
                'comment' => $comment,
                'created_at' => $at->min($now),
            ]);
        }
    }

    private function tickets(Restaurant $restaurant, CarbonImmutable $now): void
    {
        if (SupportTicket::withoutTenancy()->where('restaurant_id', $restaurant->id)->exists()) {
            return;
        }

        $owner = User::query()->whereHas('employments', fn ($query) => $query->where('restaurant_id', $restaurant->id)->where('is_owner', true))->first();
        $orders = Order::withoutTenancy()->with('items')
            ->where('restaurant_id', $restaurant->id)
            ->where('status', OrderStatus::PickedUp->value)
            ->whereNotNull('customer_id')
            ->where('picked_up_at', '>=', $now->subDays(6))
            ->latest('picked_up_at')
            ->limit(3)
            ->get();

        $stories = [
            // [category, guest says, staff reply, status, hours ago]
            [TicketCategory::MissingItem, 'The fries were missing from my bag.', null, SupportTicket::OPEN, 3],
            [TicketCategory::Quality, 'The burger was cold when I opened it at home.', 'So sorry about that. We are checking the warmer at the window today.', SupportTicket::ANSWERED, 20],
            [TicketCategory::WrongItem, 'I ordered a large karak and got a small one.', 'Our mistake, sorry. A free drink is waiting in your wallet.', SupportTicket::RESOLVED, 52],
        ];

        foreach ($orders->values() as $i => $order) {
            [$category, $said, $reply, $status, $hoursAgo] = $stories[$i];
            $opened = $now->subHours($hoursAgo);

            $ticket = SupportTicket::withoutTenancy()->forceCreate([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'customer_id' => (int) $order->customer_id,
                'number' => SupportTicket::FIRST_NUMBER + $i,
                'category' => $category,
                'items' => $category->asksForItems() ? [$order->items->first()?->id] : [],
                'status' => $status,
                'first_reply_at' => $reply === null ? null : $opened->addMinutes(25),
                'resolved_at' => $status === SupportTicket::RESOLVED ? $opened->addHour() : null,
                'resolved_by' => $status === SupportTicket::RESOLVED ? $owner?->id : null,
                'created_at' => $opened,
                'updated_at' => $opened,
            ]);

            $this->line($ticket, TicketMessage::GUEST, null, $said, $opened);

            if ($reply !== null) {
                $this->line($ticket, TicketMessage::STAFF, $owner?->id, $reply, $opened->addMinutes(25));
            }

            if ($status === SupportTicket::RESOLVED && $owner !== null) {
                $reward = Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', 'Free drink')->first();
                $customer = Customer::withoutTenancy()->find($order->customer_id);

                if ($reward !== null && $customer !== null) {
                    $wallet = app(GrantReward::class)->handle($customer, $reward, 'support', null);
                    $ticket->forceFill(['outcome' => 'reward', 'wallet_reward_id' => $wallet->id])->save();
                }
            }
        }
    }

    private function line(SupportTicket $ticket, string $author, ?int $userId, string $body, CarbonImmutable $at): void
    {
        TicketMessage::withoutTenancy()->create([
            'restaurant_id' => $ticket->restaurant_id,
            'support_ticket_id' => $ticket->id,
            'author' => $author,
            'user_id' => $userId,
            'body' => $body,
            'created_at' => $at,
        ]);
    }

    private function birthdays(Restaurant $restaurant, CarbonImmutable $now): void
    {
        $reward = Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', 'Free drink')->first()
            ?? Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('is_active', true)->orderBy('id')->first();

        if ($reward === null) {
            return;
        }

        if (! BirthdaySettings::for($restaurant)->enabled) {
            BirthdaySettings::save($restaurant, ['enabled' => true, 'reward_id' => $reward->id, 'days_before' => BirthdaySettings::DEFAULT_DAYS_BEFORE, 'send_message' => true]);
        }

        // Two guests with a birthday in the next few days.
        $local = $now->setTimezone($restaurant->timezone);

        foreach (['+97455552005' => 2, '+97455552008' => 5] as $phone => $inDays) {
            $day = $local->addDays($inDays);
            Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('phone', $phone)->whereNull('birthday')
                ->update(['birthday' => sprintf('%d-%02d-%02d', 1990 + $inDays, $day->month, $day->day)]);
        }
    }

    private function badges(Restaurant $restaurant, CarbonImmutable $now): void
    {
        if (! BadgeSettings::for($restaurant)->enabled) {
            BadgeSettings::save($restaurant, ['enabled' => true]);
        }

        $settings = BadgeSettings::for($restaurant);

        Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('picked_up_count', '>', 0)->orderBy('id')->each(function (Customer $guest) use ($settings, $now): void {
            foreach (BadgeProgress::for($guest, $settings, $now) as $key => $row) {
                if ($row['earned'] !== null || $row['current'] < $row['target'] || ! $settings->isOn(Badge::from($key))) {
                    continue;
                }

                CustomerBadge::withoutTenancy()->insertOrIgnore([
                    'restaurant_id' => $guest->restaurant_id,
                    'customer_id' => $guest->id,
                    'badge' => $key,
                    'earned_at' => $now->subDays(random_int(1, 20)),
                    'seen_at' => $now,
                ]);
            }
        });
    }
}
