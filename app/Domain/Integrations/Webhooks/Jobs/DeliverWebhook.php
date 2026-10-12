<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks\Jobs;

use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Webhooks\SendWebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends one webhook delivery attempt; retries are scheduled by webhooks:retry, not by the queue. */
final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(SendWebhookDelivery $send): void
    {
        $delivery = WebhookDelivery::withoutTenancy()->find($this->deliveryId);

        if ($delivery !== null && $delivery->status === WebhookDelivery::PENDING) {
            $send->attempt($delivery);
        }
    }
}
