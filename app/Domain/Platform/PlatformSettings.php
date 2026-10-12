<?php

declare(strict_types=1);

namespace App\Domain\Platform;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide settings edited by the super admin, stored as key/value JSON
 * and cached. Every key has a default, so a fresh install works untouched.
 */
final class PlatformSettings
{
    private const CACHE_KEY = 'platform_settings.v1';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Billing
            'billing.trial_days' => 14,
            'billing.invoice_due_days' => 7,
            'billing.grace_days' => 7,
            'billing.tax_rate_bp' => 0,
            'billing.currency' => 'QAR',
            'billing.legal_name' => 'Drive-Thru Plus',
            'billing.tax_number' => null,
            'billing.address' => 'Doha, Qatar',
            'billing.email' => 'billing@drivethru.test',
            'billing.bank_details' => null,
            // Operations
            'orders.admin_override' => true,
            'otp.channels' => ['whatsapp', 'sms'],
            // Workspace banner shown to every restaurant (maintenance and the like)
            'banner.enabled' => false,
            'banner.message' => ['ar' => null, 'en' => null],
            'banner.level' => 'warning',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $stored */
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => DB::table('platform_settings')
            ->pluck('value', 'key')
            ->map(fn (mixed $value): mixed => is_string($value) ? json_decode($value, true) : $value)
            ->all());

        return array_replace(self::defaults(), $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $known = self::defaults();

        DB::transaction(function () use ($values, $known): void {
            foreach ($values as $key => $value) {
                if (! array_key_exists($key, $known)) {
                    continue;
                }

                DB::table('platform_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now()],
                );
            }
        });

        Cache::forget(self::CACHE_KEY);
    }

    public function int(string $key): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : (int) self::defaults()[$key];
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
