<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Actions;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Campaigns\Audience;
use App\Domain\Campaigns\DeliverCampaignMessage;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Exceptions\CampaignProblem;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Crm\CrmSettings;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A campaign's life: scheduled by the owner, started when it is due (the
 * audience is written down then), sent a slice at a time by the minute job,
 * and finished or cancelled. Each run sends at most 30 messages per
 * restaurant per minute, never in quiet hours, and never past the
 * restaurant's daily limit; whatever is held back waits for the next run.
 */
final class RunCampaign
{
    public const PER_MINUTE = 30;

    public function __construct(
        private readonly Audience $audience,
        private readonly DeliverCampaignMessage $deliver,
    ) {}

    /** Schedules a draft (or reschedules); null sends it at the next minute. */
    public function schedule(Campaign $campaign, ?CarbonImmutable $at, User $by): void
    {
        if (! $campaign->status->isEditable()) {
            throw CampaignProblem::because('not_editable');
        }

        if ($at !== null && $at->isPast()) {
            $at = null;
        }

        $campaign->forceFill(['status' => CampaignStatus::Scheduled, 'scheduled_at' => $at ?? now()])->save();

        activity()->performedOn($campaign)->causedBy($by)->event('campaign.scheduled')
            ->withProperties(['restaurant_id' => $campaign->restaurant_id, 'at' => $campaign->scheduled_at?->toIso8601String()])->log('campaign.scheduled');
    }

    public function cancel(Campaign $campaign, User $by): void
    {
        if (! $campaign->status->isCancellable()) {
            throw CampaignProblem::because('not_cancellable');
        }

        DB::transaction(function () use ($campaign): void {
            $campaign->forceFill(['status' => CampaignStatus::Cancelled, 'finished_at' => now()])->save();

            CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)->where('status', CampaignMessage::PENDING)
                ->update(['status' => CampaignMessage::SKIPPED, 'skip_reason' => 'cancelled', 'updated_at' => now()]);
        });

        $this->recount($campaign);

        activity()->performedOn($campaign)->causedBy($by)->event('campaign.cancelled')
            ->withProperties(['restaurant_id' => $campaign->restaurant_id])->log('campaign.cancelled');
    }

    /** Writes down who will receive it (guests who agree right now) and starts sending. */
    public function start(Campaign $campaign): void
    {
        $claimed = Campaign::withoutTenancy()->whereKey($campaign->id)->where('status', CampaignStatus::Scheduled->value)
            ->update(['status' => CampaignStatus::Sending->value, 'started_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $restaurant = Restaurant::query()->findOrFail($campaign->restaurant_id);
        $fallback = in_array($restaurant->default_locale, ['ar', 'en'], true) ? $restaurant->default_locale : 'ar';

        $this->audience->query($campaign->restaurant_id, $campaign->segmentList())
            ->select(['id', 'locale'])
            ->chunkById(500, function ($guests) use ($campaign, $fallback): void {
                $now = now();

                CampaignMessage::withoutTenancy()->insertOrIgnore($guests->map(fn ($guest): array => [
                    'restaurant_id' => $campaign->restaurant_id,
                    'campaign_id' => $campaign->id,
                    'customer_id' => $guest->id,
                    'locale' => in_array($guest->locale, ['ar', 'en'], true) ? $guest->locale : $fallback,
                    'status' => CampaignMessage::PENDING,
                    'revenue_minor' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        $campaign->refresh();
        $campaign->forceFill(['recipients_count' => CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)->count()])->save();
    }

    /**
     * One run: sends the next slice, or works out when it may continue.
     */
    public function send(Campaign $campaign, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $campaign = Campaign::withoutTenancy()->with('reward')->find($campaign->id);

        if ($campaign === null || $campaign->status !== CampaignStatus::Sending) {
            return;
        }

        $restaurant = Restaurant::query()->findOrFail($campaign->restaurant_id);

        if (! $restaurant->hasFeature(PlanFeature::Campaigns)) {
            $this->holdUntil($campaign, $now->addHour());

            return;
        }

        $settings = CrmSettings::for($restaurant);

        if (($until = $settings->quietUntil($restaurant, $now)) !== null) {
            $this->holdUntil($campaign, $until);

            return;
        }

        $dayStart = $now->setTimezone($restaurant->timezone)->startOfDay();
        $sentToday = CampaignMessage::withoutTenancy()->where('restaurant_id', $restaurant->id)
            ->whereIn('status', CampaignMessage::COUNTED)->where('sent_at', '>=', $dayStart->utc())->count();
        $room = $settings->int('daily_message_limit') - $sentToday;

        if ($room <= 0) {
            $this->holdUntil($campaign, $dayStart->addDay()->utc());

            return;
        }

        $key = 'campaigns:'.$restaurant->id;
        $rows = CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)->where('status', CampaignMessage::PENDING)
            ->orderBy('id')->limit(min($room, self::PER_MINUTE))->get();

        foreach ($rows as $row) {
            if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
                break;
            }

            if ($this->deliver->handle($row, $campaign, $restaurant)) {
                RateLimiter::hit($key, 60);
            }
        }

        // A run that died while calling the provider: those guests are marked failed, not stuck.
        CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)->where('status', CampaignMessage::SENDING)
            ->where('sent_at', '<', $now->subMinutes(10))
            ->update(['status' => CampaignMessage::FAILED, 'sent_at' => null, 'updated_at' => now()]);

        $campaign->forceFill(['next_attempt_at' => null])->save();
        $this->recount($campaign);

        $left = CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)
            ->whereIn('status', [CampaignMessage::PENDING, CampaignMessage::SENDING])->exists();

        if (! $left) {
            Campaign::withoutTenancy()->whereKey($campaign->id)->where('status', CampaignStatus::Sending->value)
                ->update(['status' => CampaignStatus::Sent->value, 'finished_at' => now(), 'updated_at' => now()]);
        }
    }

    public function recount(Campaign $campaign): void
    {
        $counts = CampaignMessage::withoutTenancy()->where('campaign_id', $campaign->id)
            ->groupBy('status')->selectRaw('status, count(*) as total')->pluck('total', 'status');

        $campaign->forceFill([
            'sent_count' => (int) ($counts[CampaignMessage::SENT] ?? 0),
            'failed_count' => (int) ($counts[CampaignMessage::FAILED] ?? 0),
            'skipped_count' => (int) ($counts[CampaignMessage::SKIPPED] ?? 0),
        ])->save();
    }

    private function holdUntil(Campaign $campaign, CarbonImmutable $at): void
    {
        $campaign->forceFill(['next_attempt_at' => $at])->save();
    }
}
