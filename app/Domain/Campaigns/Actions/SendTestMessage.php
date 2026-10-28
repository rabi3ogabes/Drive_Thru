<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Actions;

use App\Domain\Campaigns\CampaignText;
use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Exceptions\CampaignProblem;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sends the campaign to the owner's own phone, exactly as a guest would see
 * it, so the text and the template can be checked before anyone else gets
 * it. It counts towards no guest's limits and grants no reward.
 */
final class SendTestMessage
{
    public const PER_HOUR = 5;

    public function __construct(private readonly Messenger $messenger) {}

    /** The phone the test went to. */
    public function handle(Campaign $campaign, string $phone, string $locale, User $by): string
    {
        if ($campaign->channel === CampaignChannel::Push) {
            throw CampaignProblem::because('push_test');
        }

        $to = Phone::normalize($phone);

        if ($to === null) {
            throw CampaignProblem::because('test_phone');
        }

        $key = 'campaign-test:'.$by->id;

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw CampaignProblem::because('test_limit');
        }

        RateLimiter::hit($key, 3600);

        $restaurant = Restaurant::query()->findOrFail($campaign->restaurant_id);
        $text = CampaignText::make($campaign, $restaurant, $locale, route('customer.account', $restaurant));

        $delivery = $this->messenger->send(new Message(
            phone: $to,
            purpose: Message::CAMPAIGN,
            text: $text->sms(),
            parameters: $text->whatsappParameters(),
            locale: $text->locale,
            restaurantId: $campaign->restaurant_id,
            subject: $campaign,
            channels: $campaign->channel->messengerChannels(),
        ));

        if ($delivery === null) {
            throw CampaignProblem::because('test_failed');
        }

        activity()->performedOn($campaign)->causedBy($by)->event('campaign.test_sent')
            ->withProperties(['restaurant_id' => $campaign->restaurant_id, 'channel' => $delivery->channel])->log('campaign.test_sent');

        return $to;
    }
}
