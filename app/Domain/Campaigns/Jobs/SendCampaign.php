<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Jobs;

use App\Domain\Campaigns\Actions\RunCampaign;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One sending run of a campaign; never two at once for the same campaign. */
final class SendCampaign implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $campaignId) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function handle(RunCampaign $run): void
    {
        $campaign = Campaign::withoutTenancy()->find($this->campaignId);

        if ($campaign !== null) {
            $run->send($campaign);
        }
    }
}
