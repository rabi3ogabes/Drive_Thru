<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Webhooks\Jobs\DeliverWebhook;
use Illuminate\Console\Command;

final class RetryWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Queue webhook deliveries whose next retry is due';

    public function handle(): int
    {
        $due = WebhookDelivery::withoutTenancy()
            ->where('status', WebhookDelivery::PENDING)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')
            ->limit(500)
            ->pluck('id');

        $due->each(fn (int $id) => DeliverWebhook::dispatch($id));
        $this->components->info("Queued {$due->count()} webhook retries.");

        return self::SUCCESS;
    }
}
