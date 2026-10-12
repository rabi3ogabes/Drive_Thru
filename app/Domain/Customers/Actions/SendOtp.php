<?php

declare(strict_types=1);

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Exceptions\OtpProblem;
use App\Domain\Customers\Models\OtpRequest;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Tenancy\Models\Restaurant;
use App\Support\Lang;
use App\Support\Phone;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sends a 6-digit sign-in code (WhatsApp first, SMS fallback). Limits:
 * one send per 30 s, 5 per phone and 10 per IP per 10 minutes. The code is
 * stored only as an HMAC and expires after 5 minutes.
 *
 * Returns the plain code only in the local environment (shown as a demo
 * hint); everywhere else it exists only in the message.
 */
final class SendOtp
{
    public const EXPIRES_MINUTES = 5;

    public const RESEND_SECONDS = 30;

    public const MAX_PER_PHONE = 5;

    public const MAX_PER_IP = 10;

    public const WINDOW_SECONDS = 600;

    public function __construct(private readonly Messenger $messenger) {}

    /**
     * @return array{phone: string, channel: string, resend_in: int, demo_code: ?string}
     *
     * @throws OtpProblem
     */
    public function handle(Restaurant $restaurant, string $phoneInput, ?string $ip): array
    {
        $phone = Phone::normalize($phoneInput) ?? throw new OtpProblem('phone', Lang::text('customers.otp.errors.phone'));

        $last = OtpRequest::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->where('phone', $phone)
            ->latest('id')
            ->first();

        if ($last !== null && $last->created_at->greaterThan(now()->subSeconds(self::RESEND_SECONDS))) {
            $wait = self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now());
            throw new OtpProblem('resend', Lang::text('customers.otp.errors.wait', ['seconds' => max(1, $wait)]), max(1, $wait));
        }

        foreach ([["otp:phone:{$restaurant->id}:{$phone}", self::MAX_PER_PHONE], ['otp:ip:'.($ip ?? 'none'), self::MAX_PER_IP]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new OtpProblem('limit', Lang::text('customers.otp.errors.limit', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
            }
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $delivery = $this->messenger->send(new Message(
            phone: $phone,
            purpose: Message::OTP,
            text: Lang::text('customers.otp.message', ['code' => $code, 'restaurant' => $restaurant->name, 'minutes' => self::EXPIRES_MINUTES]),
            parameters: [$code],
            locale: app()->getLocale(),
            restaurantId: $restaurant->id,
            buttonParameter: $code,
        ));
        $channel = $delivery?->channel;

        if ($channel === null) {
            throw new OtpProblem('delivery', Lang::text('customers.otp.errors.delivery'));
        }

        RateLimiter::hit("otp:phone:{$restaurant->id}:{$phone}", self::WINDOW_SECONDS);
        RateLimiter::hit('otp:ip:'.($ip ?? 'none'), self::WINDOW_SECONDS);

        OtpRequest::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'phone' => $phone,
            'code_hash' => self::hash($restaurant->id, $phone, $code),
            'channel' => $channel,
            'ip' => $ip,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ]);

        return [
            'phone' => $phone,
            'channel' => $channel,
            'resend_in' => self::RESEND_SECONDS,
            'demo_code' => app()->environment('local', 'testing') ? $code : null,
        ];
    }

    public static function hash(int $restaurantId, string $phone, string $code): string
    {
        return hash_hmac('sha256', "{$restaurantId}|{$phone}|{$code}", (string) config('app.key'));
    }
}
