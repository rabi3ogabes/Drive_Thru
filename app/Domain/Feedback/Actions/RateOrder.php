<?php

declare(strict_types=1);

namespace App\Domain\Feedback\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Feedback\Exceptions\RatingRefused;
use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Feedback\RatingTags;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\StaffAlerts;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Filament\App\Resources\Ratings\RatingResource;
use Illuminate\Database\QueryException;

/**
 * Saves the guest's rating of their own picked-up order, once, within 48
 * hours. Tags that don't fit the stars are dropped. One or two stars tell
 * the branch's managers straight away.
 */
final class RateOrder
{
    public const WINDOW_HOURS = 48;

    public const COMMENT_LENGTH = 280;

    public function __construct(private readonly StaffAlerts $alerts) {}

    public function canRate(Order $order, ?Customer $customer): bool
    {
        return $customer !== null && $this->problem($order, $customer) === null
            && ! OrderRating::withoutTenancy()->where('order_id', $order->id)->exists();
    }

    /**
     * @param  array<int, mixed>  $tags
     *
     * @throws RatingRefused
     */
    public function handle(Order $order, Customer $customer, int $stars, array $tags, ?string $comment): OrderRating
    {
        if ($stars < 1 || $stars > 5) {
            throw new RatingRefused('invalid');
        }

        if (($problem = $this->problem($order, $customer)) !== null) {
            throw new RatingRefused($problem);
        }

        $comment = trim((string) $comment);

        try {
            $rating = OrderRating::withoutTenancy()->create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'stars' => $stars,
                'tags' => array_values(array_intersect(RatingTags::for($stars), array_filter($tags, 'is_string'))),
                'comment' => $comment === '' ? null : mb_substr($comment, 0, self::COMMENT_LENGTH),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            throw new RatingRefused('already_rated'); // a second tap, or a second tab
        }

        if ($rating->isLow()) {
            $this->alert($order, $rating);
        }

        return $rating;
    }

    private function problem(Order $order, Customer $customer): ?string
    {
        return match (true) {
            $order->customer_id !== $customer->id || $order->restaurant_id !== $customer->restaurant_id => 'not_yours',
            $order->status !== OrderStatus::PickedUp => 'not_picked_up',
            ($order->picked_up_at ?? $order->updated_at)?->lt(now()->subHours(self::WINDOW_HOURS)) ?? true => 'too_late',
            OrderRating::withoutTenancy()->where('order_id', $order->id)->exists() => 'already_rated',
            default => null,
        };
    }

    private function alert(Order $order, OrderRating $rating): void
    {
        $order->loadMissing('branch.restaurant');

        $this->alerts->send(AlertKind::LowRating, $order->restaurant_id, $order->branch, [
            'number' => $order->number,
            'stars' => $rating->stars,
            'branch' => $order->branch->getTranslations('name'),
        ], RatingResource::getUrl(panel: 'app', tenant: $order->branch->restaurant).'?'.http_build_query(['tableFilters' => ['stars' => ['value' => $rating->stars]]]));
    }
}
