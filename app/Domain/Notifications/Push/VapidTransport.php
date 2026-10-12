<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Push;

use App\Domain\Notifications\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Standard Web Push with our own VAPID keys: messages go straight to the
 * browser vendor's push service, encrypted end to end. No third-party
 * notification service is involved.
 */
final class VapidTransport implements PushTransport
{
    private ?WebPush $client = null;

    public function configured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        try {
            $report = $this->client()->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => 'aes128gcm',
                ]),
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ['TTL' => 3600, 'urgency' => 'high'],
            );
        } catch (Throwable $e) {
            report($e);

            return self::FAILED;
        }

        if ($report->isSuccess()) {
            return self::SENT;
        }

        return $report->isSubscriptionExpired() ? self::EXPIRED : self::FAILED;
    }

    private function client(): WebPush
    {
        return $this->client ??= new WebPush(['VAPID' => [
            'subject' => (string) config('webpush.subject'),
            'publicKey' => (string) config('webpush.public_key'),
            'privateKey' => (string) config('webpush.private_key'),
        ]]);
    }
}
