<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Crm\UnsubscribeLink;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Notifications\Push\WebPushSender;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Sends one campaign message, deciding at that moment whether it may go:
 * the guest must still accept offers, and must not have had a campaign
 * message in the last 24 hours or three in the last 7 days. The cap is
 * checked and claimed under a per-guest lock, so two campaigns running at
 * once can't both get through.
 */
final class DeliverCampaignMessage
{
    public const PER_DAY = 1;

    public const PER_WEEK = 3;

    public function __construct(
        private readonly Messenger $messenger,
        private readonly WebPushSender $push,
        private readonly GrantReward $grantReward,
    ) {}

    /** False when the guest's lock was busy: the message stays pending for the next run. */
    public function handle(CampaignMessage $row, Campaign $campaign, Restaurant $restaurant): bool
    {
        $customer = Customer::withoutTenancy()->find($row->customer_id);

        if ($customer === null || ! $customer->acceptsOffers()) {
            $this->skip($row, 'no_consent');

            return true;
        }

        if ($campaign->channel === CampaignChannel::Push && (! $this->push->available() || ! $this->push->hasSubscribers($customer))) {
            $this->skip($row, 'no_push');

            return true;
        }

        try {
            $claimed = Cache::lock('campaign-cap:'.$customer->id, 10)->block(5, fn (): bool => $this->claim($row));
        } catch (LockTimeoutException) {
            return false;
        }

        if (! $claimed) {
            $this->skip($row, 'frequency_cap');

            return true;
        }

        $text = CampaignText::make($campaign, $restaurant, $row->locale, UnsubscribeLink::url($customer, $restaurant));
        [$sent, $channel, $deliveryId] = $this->send($campaign, $customer, $row, $text);

        $row->forceFill([
            'status' => $sent ? CampaignMessage::SENT : CampaignMessage::FAILED,
            'channel' => $channel,
            'message_delivery_id' => $deliveryId,
            'sent_at' => $sent ? $row->sent_at : null,
        ])->save();

        if ($sent && $campaign->reward !== null) {
            try {
                $this->grantReward->handle($customer, $campaign->reward, 'campaign', null);
            } catch (RewardUnavailable) {
                // The reward was switched off after the campaign was written: the message still counts.
            }
        }

        return true;
    }

    /** Within the lock: does the guest have room under the cap? If so, hold the place. */
    private function claim(CampaignMessage $row): bool
    {
        $recent = CampaignMessage::withoutTenancy()
            ->where('customer_id', $row->customer_id)
            ->whereIn('status', CampaignMessage::COUNTED)
            ->where('sent_at', '>=', now()->subDays(7))
            ->pluck('sent_at');

        $today = $recent->filter(fn ($at): bool => $at !== null && $at->gte(now()->subDay()))->count();

        if ($today >= self::PER_DAY || $recent->count() >= self::PER_WEEK) {
            return false;
        }

        $row->forceFill(['status' => CampaignMessage::SENDING, 'sent_at' => now()])->save();

        return true;
    }

    /**
     * @return array{bool, ?string, ?int}
     */
    private function send(Campaign $campaign, Customer $customer, CampaignMessage $row, CampaignText $text): array
    {
        if ($campaign->channel === CampaignChannel::Push) {
            $sent = $this->push->send($customer, $text->push('campaign-'.$campaign->id), $campaign->restaurant_id) > 0;

            return [$sent, 'push', null];
        }

        $delivery = $this->messenger->send(new Message(
            phone: $customer->phone,
            purpose: Message::CAMPAIGN,
            text: $text->sms(),
            parameters: $text->whatsappParameters(),
            locale: $text->locale,
            restaurantId: $campaign->restaurant_id,
            subject: $row,
            channels: $campaign->channel->messengerChannels(),
        ));

        return [$delivery !== null, $delivery?->channel, $delivery?->id];
    }

    private function skip(CampaignMessage $row, string $reason): void
    {
        $row->forceFill(['status' => CampaignMessage::SKIPPED, 'skip_reason' => $reason])->save();
    }
}
