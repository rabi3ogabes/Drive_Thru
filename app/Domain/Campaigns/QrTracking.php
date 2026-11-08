<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\Models\QrScan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Counting tracked QR codes honestly: one scan per device per 30 minutes,
 * link previews and crawlers marked as bots (kept, never counted), and a
 * visit only when that scan reached the menu. Devices are a salted hash.
 */
final class QrTracking
{
    public const DEDUPE_MINUTES = 30;

    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|slack|discord|skype|curl|wget|python|httpclient|headless|lighthouse|monitor/i';

    /** Records the scan (unless it repeats one from the last 30 minutes) and remembers it for this visit. */
    public function scan(QrCampaign $campaign, Request $request): void
    {
        $agent = (string) $request->userAgent();
        $bot = $agent === '' || preg_match(self::BOTS, $agent) === 1;
        $device = hash_hmac('sha256', $request->ip().'|'.$agent, 'qr|'.config('app.key'));
        $now = CarbonImmutable::now();

        $scan = QrScan::withoutTenancy()
            ->where('qr_campaign_id', $campaign->id)
            ->where('device_hash', $device)
            ->where('scanned_at', '>=', $now->subMinutes(self::DEDUPE_MINUTES))
            ->latest('id')
            ->first();

        $scan ??= QrScan::withoutTenancy()->create([
            'restaurant_id' => $campaign->restaurant_id,
            'qr_campaign_id' => $campaign->id,
            'device_hash' => $device,
            'is_bot' => $bot,
            'scanned_at' => $now,
        ]);

        if (! $bot && $request->hasSession()) {
            $request->session()->put($this->key($campaign->restaurant_id), $scan->id);
        }
    }

    /** The guest reached the menu from a tracked QR scan: that scan became a visit. */
    public function visited(int $restaurantId): void
    {
        $id = session()->pull($this->key($restaurantId));

        if (is_int($id)) {
            QrScan::withoutTenancy()->whereKey($id)->where('restaurant_id', $restaurantId)->whereNull('visited_at')
                ->update(['visited_at' => now()]);
        }
    }

    /**
     * Where the code leads, carrying what the order should remember.
     */
    public function destination(QrCampaign $campaign): string
    {
        $restaurant = $campaign->restaurant;
        $base = $campaign->branch !== null
            ? route('public.branch', [$restaurant, $campaign->branch->code])
            : route('public.restaurant', $restaurant);

        return $base.'?'.http_build_query(array_filter([
            'source' => 'qr',
            'campaign' => $campaign->code,
            'channel' => $campaign->placement,
            'lane' => $campaign->lane,
            'promo' => $campaign->promo,
        ], fn (?string $value): bool => filled($value)));
    }

    private function key(int $restaurantId): string
    {
        return "qr_scan.{$restaurantId}";
    }
}
