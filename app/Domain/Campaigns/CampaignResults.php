<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Messaging\Models\MessageDelivery;
use Illuminate\Support\Collection;

/**
 * What a campaign achieved, for its page: delivery reports from the
 * providers, why guests were skipped, and the latest recipients. The
 * counters on the campaign itself cover sent, failed, orders and revenue.
 */
final class CampaignResults
{
    /**
     * @return array{delivered: int, done: int, skips: array<string, int>, recent: Collection<int, CampaignMessage>}
     */
    public static function for(Campaign $campaign): array
    {
        $delivered = CampaignMessage::withoutTenancy()
            ->where('campaign_messages.campaign_id', $campaign->id)
            ->join('message_deliveries', 'message_deliveries.id', '=', 'campaign_messages.message_delivery_id')
            ->whereIn('message_deliveries.status', [MessageDelivery::DELIVERED, MessageDelivery::READ])
            ->count();

        // Push has no delivery report: a sent notification counts as delivered.
        $delivered += CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)
            ->where('status', CampaignMessage::SENT)->where('channel', 'push')->count();

        /** @var array<string, int> $skips */
        $skips = CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)->where('status', CampaignMessage::SKIPPED)
            ->groupBy('skip_reason')->selectRaw('skip_reason, count(*) as total')->pluck('total', 'skip_reason')
            ->map(fn (mixed $total): int => (int) $total)->sortDesc()->all();

        $recent = CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)
            ->whereIn('status', [CampaignMessage::SENT, CampaignMessage::FAILED])
            ->with('customer:id,restaurant_id,name,phone')
            ->latest('sent_at')->latest('id')->limit(8)->get();

        return [
            'delivered' => $delivered,
            'done' => $campaign->sent_count + $campaign->failed_count + $campaign->skipped_count,
            'skips' => $skips,
            'recent' => $recent,
        ];
    }
}
