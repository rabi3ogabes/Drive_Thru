<?php

declare(strict_types=1);

namespace App\Domain\Loyalty;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampEvent;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Ordering\Data\PricedLine;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Lang;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stamp cards. Each counted item adds a stamp when the order is picked up
 * (or when the counter adds them by hand), up to a daily cap per guest. A
 * full card turns into a wallet reward and starts again, keeping any extra
 * stamps. A cancelled or refunded order takes its stamps back; if that
 * undoes a reward the guest hasn't used, the reward is withdrawn too.
 * Every change is a StampEvent, written under a lock on the card.
 */
final class Stamps
{
    public function __construct(
        private readonly GrantReward $grant,
        private readonly Messenger $messenger,
    ) {}

    public function enabledFor(Restaurant $restaurant): bool
    {
        return $restaurant->hasFeature(PlanFeature::Loyalty);
    }

    /**
     * @return Collection<int, StampProgram>
     */
    public function programs(int $restaurantId): Collection
    {
        return StampProgram::withoutTenancy()->with('reward.product')
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /**
     * How many items of the order count for the program.
     *
     * @param  iterable<OrderItem>  $items
     */
    public function counted(StampProgram $program, iterable $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            $matches = $program->target_type === 'category'
                ? $item->product?->menu_category_id === $program->target_id
                : $item->product_id === $program->target_id;

            if ($matches) {
                $count += $item->quantity;
            }
        }

        return $count;
    }

    /**
     * Stamps for a picked-up order, once per order.
     *
     * @return list<array{program: StampProgram, added: int, completed: int}>
     */
    public function earnForOrder(Order $order): array
    {
        $order->loadMissing(['items.product', 'customer', 'restaurant']);

        if ($order->customer === null || ! $this->enabledFor($order->restaurant)) {
            return [];
        }

        if (StampEvent::withoutTenancy()->where('order_id', $order->id)->whereIn('source', ['order', 'reversal'])->exists()) {
            return [];
        }

        $results = [];

        foreach ($this->programs($order->restaurant_id) as $program) {
            $count = $this->counted($program, $order->items);

            if ($count > 0) {
                $results[] = $this->add($order->customer, $program, $count, 'order', $order, null, null);
            }
        }

        return array_values(array_filter($results, fn (array $r): bool => $r['added'] > 0));
    }

    /**
     * Stamps added by the counter (a guest who paid at the till).
     *
     * @return array{program: StampProgram, added: int, completed: int}
     */
    public function addAtCounter(Customer $customer, StampProgram $program, int $quantity, User $actor, ?string $note = null): array
    {
        abort_unless($program->restaurant_id === $customer->restaurant_id && $program->isLive(), 404);

        $result = $this->add($customer, $program, max(1, min(20, $quantity)), 'counter', null, $actor, $note);

        if ($result['added'] > 0) {
            activity()->performedOn($customer)->causedBy($actor)->event('loyalty.stamps_added')
                ->withProperties(['program_id' => $program->id, 'stamps' => $result['added'], 'note' => $note])->log('loyalty.stamps_added');
            $this->tell($customer, $program, $result);
        }

        return $result;
    }

    /**
     * What this bag will do to the guest's cards, for checkout: "adds 2
     * stamps, 1 to go" or "fills your card".
     *
     * @param  iterable<PricedLine>  $lines
     * @return list<array{program: StampProgram, adds: int, left: int, fills: bool}>
     */
    public function preview(Restaurant $restaurant, ?Customer $customer, iterable $lines): array
    {
        if (! $this->enabledFor($restaurant)) {
            return [];
        }

        $programs = $this->programs($restaurant->id);
        $cards = $customer === null ? collect() : StampCard::withoutTenancy()->where('customer_id', $customer->id)->get()->keyBy('stamp_program_id');
        $preview = [];

        foreach ($programs as $program) {
            $count = 0;

            foreach ($lines as $line) {
                $matches = $program->target_type === 'category'
                    ? $line->product?->menu_category_id === $program->target_id
                    : $line->product?->id === $program->target_id;
                $count += $matches && $line->isValid() ? $line->quantity : 0;
            }

            if ($count === 0) {
                continue;
            }

            $card = $cards->get($program->id);
            $todayLeft = $card === null ? $program->daily_cap : max(0, $program->daily_cap - $this->todayOn($card, $restaurant->timezone));
            $adds = min($count, $todayLeft);
            $after = (int) ($card->stamps ?? 0) + $adds;

            if ($adds > 0) {
                $preview[] = ['program' => $program, 'adds' => $adds, 'left' => max(0, $program->stamps_needed - $after), 'fills' => $after >= $program->stamps_needed];
            }
        }

        return $preview;
    }

    /** Takes back the stamps an order earned (cancelled after pickup, or fully refunded). */
    public function reverseOrder(Order $order, string $note): int
    {
        $taken = 0;

        $earned = StampEvent::withoutTenancy()->where('order_id', $order->id)->where('source', 'order')
            ->groupBy('stamp_card_id')->selectRaw('stamp_card_id, sum(delta) as stamps')->pluck('stamps', 'stamp_card_id');

        foreach ($earned as $cardId => $stamps) {
            DB::transaction(function () use ($cardId, $stamps, $order, $note, &$taken): void {
                $card = StampCard::withoutTenancy()->with('program')->whereKey($cardId)->lockForUpdate()->first();

                if ($card === null || StampEvent::withoutTenancy()->where('order_id', $order->id)->where('stamp_card_id', $card->id)->where('source', 'reversal')->exists()) {
                    return;
                }

                $due = (int) $stamps;
                $fromCard = min($due, $card->stamps);
                $left = $due - $fromCard;
                $card->stamps -= $fromCard;

                // The order's stamps completed a card: withdraw that reward if it's still unused.
                if ($left > 0) {
                    $completion = StampEvent::withoutTenancy()->where('stamp_card_id', $card->id)->where('source', 'completed')->whereNotNull('wallet_reward_id')->latest('id')->first();
                    $wallet = $completion === null ? null : WalletReward::withoutTenancy()->whereKey($completion->wallet_reward_id)->lockForUpdate()->first();

                    if ($wallet !== null && $wallet->status === WalletReward::AVAILABLE) {
                        $wallet->forceFill(['status' => WalletReward::CANCELLED])->save();
                        $card->stamps = max(0, $card->program->stamps_needed - $left);
                        $card->completed_count = max(0, $card->completed_count - 1);
                        $note .= ' · reward withdrawn';
                    } else {
                        $note .= ' · reward already used';
                    }
                }

                $card->save();
                $this->event($card, -$due, 'reversal', $order, null, mb_substr($note, 0, 120));
                $taken += $due;
            });
        }

        return $taken;
    }

    /** The counter hands over a free item from the guest's wallet. */
    public function redeemAtCounter(WalletReward $wallet, User $actor): void
    {
        $updated = WalletReward::withoutTenancy()->whereKey($wallet->id)->where('status', WalletReward::AVAILABLE)
            ->update(['status' => WalletReward::USED, 'updated_at' => now()]);

        abort_unless($updated === 1, 409);

        activity()->performedOn($wallet->customer()->withoutTenancy()->first() ?? $wallet)->causedBy($actor)->event('loyalty.reward_redeemed_counter')
            ->withProperties(['wallet_reward_id' => $wallet->id, 'reward_id' => $wallet->reward_id])->log('loyalty.reward_redeemed_counter');
    }

    /** Stamps this guest got on this card today (in the restaurant's time). */
    public function todayOn(StampCard $card, string $timezone): int
    {
        return (int) StampEvent::withoutTenancy()->where('stamp_card_id', $card->id)
            ->whereIn('source', ['order', 'counter'])
            ->where('created_at', '>=', CarbonImmutable::now($timezone)->startOfDay()->utc())
            ->sum('delta');
    }

    public function cardFor(Customer $customer, StampProgram $program): StampCard
    {
        return StampCard::withoutTenancy()->firstOrCreate(
            ['customer_id' => $customer->id, 'stamp_program_id' => $program->id],
            ['restaurant_id' => $customer->restaurant_id],
        );
    }

    /**
     * @return array{program: StampProgram, added: int, completed: int}
     */
    private function add(Customer $customer, StampProgram $program, int $quantity, string $source, ?Order $order, ?User $actor, ?string $note): array
    {
        $this->cardFor($customer, $program);
        $timezone = $program->restaurant()->value('timezone') ?? 'UTC';

        return DB::transaction(function () use ($customer, $program, $quantity, $source, $order, $actor, $note, $timezone): array {
            $card = StampCard::withoutTenancy()->where('customer_id', $customer->id)->where('stamp_program_id', $program->id)->lockForUpdate()->firstOrFail();

            if ($card->expires_at !== null && $card->expires_at->isPast() && $card->stamps > 0) {
                $this->event($card, -$card->stamps, 'expired', null, null, null);
                $card->stamps = 0;
            }

            $added = min($quantity, max(0, $program->daily_cap - $this->todayOn($card, $timezone)));

            if ($added === 0) {
                $card->save();

                return ['program' => $program, 'added' => 0, 'completed' => 0];
            }

            $card->stamps += $added;
            $card->last_stamped_at = CarbonImmutable::now();
            $card->expires_at = $program->card_expiry_days !== null ? CarbonImmutable::now()->addDays($program->card_expiry_days) : null;
            $this->event($card, $added, $source, $order, $actor, $note);

            $completed = 0;

            while ($card->stamps >= $program->stamps_needed) {
                try {
                    $wallet = $this->grant->handle($customer, $program->reward, 'stamps', $actor);
                } catch (RewardUnavailable) {
                    break; // the reward is paused: the card stays full and completes next time
                }

                $card->stamps -= $program->stamps_needed;
                $card->completed_count++;
                $this->event($card, -$program->stamps_needed, 'completed', $order, $actor, null, $wallet->id);
                $completed++;
            }

            $card->save();

            return ['program' => $program, 'added' => $added, 'completed' => $completed];
        });
    }

    private function event(StampCard $card, int $delta, string $source, ?Order $order, ?User $actor, ?string $note, ?int $walletId = null): void
    {
        StampEvent::withoutTenancy()->create([
            'restaurant_id' => $card->restaurant_id,
            'stamp_card_id' => $card->id,
            'order_id' => $order?->id,
            'delta' => $delta,
            'source' => $source,
            'wallet_reward_id' => $walletId,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }

    /**
     * WhatsApp/SMS after counter stamps: "+2 stamps, 3 to go".
     *
     * @param  array{program: StampProgram, added: int, completed: int}  $result
     */
    private function tell(Customer $customer, StampProgram $program, array $result): void
    {
        $card = $this->cardFor($customer, $program);
        $locale = in_array($customer->locale, ['ar', 'en'], true) ? $customer->locale : ($program->restaurant()->value('default_locale') ?? 'ar');
        $name = $program->getTranslation('name', $locale);
        $toGo = $program->stamps_needed - $card->stamps;
        $key = $result['completed'] > 0 ? 'notifications.guest.stamps_completed' : 'notifications.guest.stamps_added';

        try {
            $this->messenger->send(new Message(
                phone: $customer->phone,
                purpose: Message::STAMPS,
                text: Lang::text($key, ['stamps' => $result['added'], 'card' => $name, 'left' => $toGo], $locale),
                parameters: [(string) $result['added'], $name, (string) $toGo],
                locale: $locale,
                restaurantId: $customer->restaurant_id,
                subject: $card,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
