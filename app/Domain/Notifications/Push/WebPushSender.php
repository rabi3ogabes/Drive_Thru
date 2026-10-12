<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Push;

use App\Domain\Integrations\Models\ProviderCall;
use App\Domain\Notifications\Models\PushSubscription;
use Illuminate\Database\Eloquent\Model;

/**
 * Sends a push notification to every browser a guest or staff member has
 * subscribed. Expired subscriptions are removed; outcomes are recorded for
 * the health page.
 */
final class WebPushSender
{
    public function __construct(private readonly PushTransport $transport) {}

    public function available(): bool
    {
        return $this->transport->configured();
    }

    public function hasSubscribers(Model $subscriber): bool
    {
        return PushSubscription::query()->where('subscriber_type', $subscriber->getMorphClass())->where('subscriber_id', $subscriber->getKey())->exists();
    }

    /**
     * @param  array{title: string, body: string, url?: string, tag?: string}  $payload
     * @return int how many browsers received it
     */
    public function send(Model $subscriber, array $payload, ?int $restaurantId = null): int
    {
        if (! $this->transport->configured()) {
            return 0;
        }

        $sent = 0;
        $subscriptions = PushSubscription::query()
            ->where('subscriber_type', $subscriber->getMorphClass())
            ->where('subscriber_id', $subscriber->getKey())
            ->latest('id')
            ->limit(10)
            ->get();

        foreach ($subscriptions as $subscription) {
            $started = hrtime(true);
            $outcome = $this->transport->send($subscription, $payload);

            if ($outcome === PushTransport::EXPIRED) {
                $subscription->delete();
            } elseif ($outcome === PushTransport::SENT) {
                $subscription->forceFill(['last_used_at' => now()])->save();
                $sent++;
            }

            if ($restaurantId !== null) {
                ProviderCall::withoutTenancy()->create([
                    'restaurant_id' => $restaurantId,
                    'provider' => 'webpush',
                    'operation' => 'push',
                    'ok' => $outcome !== PushTransport::FAILED,
                    'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                    'error' => $outcome === PushTransport::FAILED ? 'push_failed' : null,
                    'created_at' => now(),
                ]);
            }
        }

        return $sent;
    }
}
