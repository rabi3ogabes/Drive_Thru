<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Makes coupon codes for a promotion: one chosen code, or many random ones
 * from an alphabet without look-alikes (no 0/O, 1/I/L), unique within the
 * restaurant. Upper-case, so typing them is forgiving.
 */
final class GenerateCoupons
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const MAX_BATCH = 500;

    /**
     * @return list<Coupon>
     */
    public function batch(Promotion $promotion, int $count, string $prefix, int $length, ?int $usageLimit, ?int $perCustomerLimit, ?CarbonInterface $expiresAt, ?User $by): array
    {
        if ($count < 1 || $count > self::MAX_BATCH || $length < 6 || $length > 16) {
            throw new InvalidArgumentException('Out of range.');
        }

        $prefix = Coupon::normalize($prefix);

        return DB::transaction(function () use ($promotion, $count, $prefix, $length, $usageLimit, $perCustomerLimit, $expiresAt, $by): array {
            $existing = array_flip(Coupon::withoutTenancy()->where('restaurant_id', $promotion->restaurant_id)->pluck('code')->all());
            $codes = [];

            while (count($codes) < $count) {
                $code = $prefix.$this->random($length);

                if (! isset($existing[$code]) && ! isset($codes[$code])) {
                    $codes[$code] = true;
                }
            }

            $created = array_map(fn (string $code): Coupon => Coupon::withoutTenancy()->create([
                'restaurant_id' => $promotion->restaurant_id,
                'promotion_id' => $promotion->id,
                'code' => $code,
                'usage_limit' => $usageLimit,
                'per_customer_limit' => $perCustomerLimit,
                'expires_at' => $expiresAt,
            ]), array_keys($codes));

            activity()->performedOn($promotion)->causedBy($by)->event('promotion.codes_generated')->withProperties(['count' => $count])->log('promotion.codes_generated');

            return $created;
        });
    }

    /**
     * @throws InvalidArgumentException when the code is taken or malformed
     */
    public function single(Promotion $promotion, string $code, ?int $usageLimit, ?int $perCustomerLimit, ?CarbonInterface $expiresAt, ?User $by): Coupon
    {
        $code = Coupon::normalize($code);

        if (preg_match('/^[A-Z0-9_-]{3,40}$/', $code) !== 1) {
            throw new InvalidArgumentException('format');
        }

        if (Coupon::withoutTenancy()->where('restaurant_id', $promotion->restaurant_id)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('taken');
        }

        $coupon = Coupon::withoutTenancy()->create([
            'restaurant_id' => $promotion->restaurant_id,
            'promotion_id' => $promotion->id,
            'code' => $code,
            'usage_limit' => $usageLimit,
            'per_customer_limit' => $perCustomerLimit,
            'expires_at' => $expiresAt,
        ]);

        activity()->performedOn($promotion)->causedBy($by)->event('promotion.code_added')->withProperties(['code' => $code])->log('promotion.code_added');

        return $coupon;
    }

    private function random(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
