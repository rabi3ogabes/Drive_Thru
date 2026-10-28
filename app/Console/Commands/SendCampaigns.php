<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Campaigns\Actions\RunCampaign;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Jobs\SendCampaign;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/** Every minute: starts campaigns that are due and queues a sending run for each one in progress. */
#[Signature('campaigns:send')]
#[Description('Start due campaigns and queue the next sending run of each')]
final class SendCampaigns extends Command
{
    public function handle(RunCampaign $run): int
    {
        Campaign::withoutTenancy()->where('status', CampaignStatus::Scheduled->value)->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')->get()->each(fn (Campaign $campaign) => $run->start($campaign));

        $queued = 0;

        Campaign::withoutTenancy()->where('status', CampaignStatus::Sending->value)
            ->where(fn (Builder $query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->pluck('id')
            ->each(function (int $id) use (&$queued): void {
                SendCampaign::dispatch($id);
                $queued++;
            });

        $this->components->info("Queued {$queued} campaign runs.");

        return self::SUCCESS;
    }
}
