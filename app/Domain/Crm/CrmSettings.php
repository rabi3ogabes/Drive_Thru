<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;

/**
 * A restaurant's guest rules: when a guest counts as new, regular, at risk
 * or inactive, who is VIP, and the marketing guard rails. Stored under
 * restaurants.settings.crm; anything not set falls back to the defaults.
 */
final readonly class CrmSettings
{
    public const DEFAULTS = [
        'new_days' => 30,
        'regular_orders' => 3,
        'vip_top_percent' => 5,
        'high_spender_factor' => 2,
        'at_risk_days' => 30,
        'inactive_days' => 60,
        'frequent_orders' => 3,
        'frequent_products' => 20,
        'daily_message_limit' => 1000,
        'quiet_start' => '22:00',
        'quiet_end' => '09:00',
    ];

    /**
     * @param  array<string, int|string>  $values
     */
    private function __construct(private array $values) {}

    public static function for(Restaurant $restaurant): self
    {
        $stored = (array) ($restaurant->settings['crm'] ?? []);

        return new self(array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS)));
    }

    public static function fromDefaults(): self
    {
        return new self(self::DEFAULTS);
    }

    public function int(string $key): int
    {
        return max(0, (int) ($this->values[$key] ?? self::DEFAULTS[$key]));
    }

    public function time(string $key): string
    {
        $value = (string) ($this->values[$key] ?? self::DEFAULTS[$key]);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : (string) self::DEFAULTS[$key];
    }

    /**
     * When offers may go out again, if "now" is inside the quiet hours (a
     * range like 22:00–09:00 crosses midnight), in the restaurant's time zone.
     */
    public function quietUntil(Restaurant $restaurant, CarbonImmutable $now): ?CarbonImmutable
    {
        $start = $this->time('quiet_start');
        $end = $this->time('quiet_end');

        if ($start === $end) {
            return null;
        }

        $local = $now->setTimezone($restaurant->timezone);
        $time = $local->format('H:i');
        $quiet = $start < $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end);

        if (! $quiet) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $end));
        $until = $local->setTime($hour, $minute);

        return ($until <= $local ? $until->addDay() : $until)->utc();
    }

    /**
     * Keeps only known keys, as whole numbers or HH:MM times.
     *
     * @param  array<string, mixed>  $values
     */
    public static function save(Restaurant $restaurant, array $values): self
    {
        $clean = [];

        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $clean[$key] = is_int($default) ? max(0, (int) $values[$key]) : substr((string) $values[$key], 0, 5);
        }

        $settings = (array) $restaurant->settings;
        $settings['crm'] = array_merge((array) ($settings['crm'] ?? []), $clean);
        $restaurant->forceFill(['settings' => $settings])->save();

        return self::for($restaurant);
    }

    /**
     * @return array<string, int|string>
     */
    public function all(): array
    {
        return $this->values;
    }
}
