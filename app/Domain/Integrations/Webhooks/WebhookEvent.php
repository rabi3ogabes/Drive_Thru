<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Webhooks;

use App\Support\Lang;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** What another system can subscribe to. The value is the event name in every delivery. */
enum WebhookEvent: string implements HasDescription, HasLabel
{
    case OrderCreated = 'order.created';
    case OrderStatusChanged = 'order.status_changed';
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentRefunded = 'payment.refunded';

    public function getLabel(): string
    {
        return Lang::text('integrations.webhooks.events.'.$this->langKey().'.label');
    }

    public function getDescription(): string
    {
        return Lang::text('integrations.webhooks.events.'.$this->langKey().'.description');
    }

    /** "order.created" → "order_created": dots would nest the translation key. */
    private function langKey(): string
    {
        return str_replace('.', '_', $this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $event): string => $event->value, self::cases());
    }
}
