<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * One message to a phone. WhatsApp sends the approved template with its
 * parameters; SMS sends the plain text. The text is never logged.
 */
final readonly class Message
{
    public const OTP = 'otp';

    public const ORDER_RECEIVED = 'order_received';

    public const ORDER_READY = 'order_ready';

    public const RECEIPT = 'receipt';

    public const ALERT = 'alert';

    public const PAGER_READY = 'pager_ready';

    public const STAMPS = 'stamps';

    public const CAMPAIGN = 'campaign';

    /**
     * @param  list<string>  $parameters  template body parameters, in order
     * @param  list<string>|null  $channels  only these channels, in the platform's order (null = all)
     */
    public function __construct(
        public string $phone,
        public string $purpose,
        public string $text,
        public array $parameters,
        public string $locale,
        public ?int $restaurantId = null,
        public ?Model $subject = null,
        public ?string $buttonParameter = null,
        public ?array $channels = null,
    ) {}

    public function isOtp(): bool
    {
        return $this->purpose === self::OTP;
    }

    public function maskedPhone(): string
    {
        return substr($this->phone, 0, 4).'••••'.substr($this->phone, -2);
    }

    public function phoneHash(): string
    {
        return hash('sha256', $this->phone);
    }
}
