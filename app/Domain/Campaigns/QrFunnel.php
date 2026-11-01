<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\Models\QrScan;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Carbon\CarbonImmutable;

/**
 * What a tracked QR code brought in: scans (devices, bots left out) →
 * visits (scans that reached the menu) → orders placed through it (not
 * cancelled) → revenue (picked-up orders) → conversion (orders per visit),
 * plus scans and orders per day in the restaurant's time zone.
 */
final class QrFunnel
{
    /**
     * @return array{
     *     scans: int, visits: int, orders: int, revenue_minor: int, conversion: ?float,
     *     days: list<array{date: string, scans: int, orders: int}>
     * }
     */
    public static function for(QrCampaign $campaign, string $timezone, int $days = 30): array
    {
        $scans = QrScan::withoutTenancy()->where('qr_campaign_id', $campaign->id)->where('is_bot', false);
        $orders = Order::withoutTenancy()
            ->where('restaurant_id', $campaign->restaurant_id)
            ->where('source->campaign', $campaign->code)
            ->where('status', '!=', OrderStatus::Cancelled->value);

        $visits = (clone $scans)->whereNotNull('visited_at')->count();
        $orderCount = (clone $orders)->count();

        $since = CarbonImmutable::now($timezone)->startOfDay()->subDays($days - 1);
        $series = [];

        for ($day = $since; $day->lte(CarbonImmutable::now($timezone)); $day = $day->addDay()) {
            $series[$day->toDateString()] = ['date' => $day->toDateString(), 'scans' => 0, 'orders' => 0];
        }

        (clone $scans)->where('scanned_at', '>=', $since->utc())->pluck('scanned_at')
            ->each(function (CarbonImmutable $at) use (&$series, $timezone): void {
                $key = $at->setTimezone($timezone)->toDateString();
                if (isset($series[$key])) {
                    $series[$key]['scans']++;
                }
            });

        (clone $orders)->where('placed_at', '>=', $since->utc())->pluck('placed_at')
            ->each(function (mixed $at) use (&$series, $timezone): void {
                $key = CarbonImmutable::parse($at)->setTimezone($timezone)->toDateString();
                if (isset($series[$key])) {
                    $series[$key]['orders']++;
                }
            });

        return [
            'scans' => (clone $scans)->count(),
            'visits' => $visits,
            'orders' => $orderCount,
            'revenue_minor' => (int) (clone $orders)->where('status', OrderStatus::PickedUp->value)->sum('total_minor'),
            'conversion' => $visits > 0 ? round($orderCount / $visits * 100, 1) : null,
            'days' => array_values($series),
        ];
    }
}
