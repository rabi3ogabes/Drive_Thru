<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Menu\Models\Product;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Seeder;

/**
 * Demo loyalty and offers for one restaurant, as an owner might set them up:
 * a points program, four tiers, three rewards, a happy hour and a welcome
 * code. Demo data only; new restaurants start with loyalty off and empty.
 */
final class DemoGrowthSeeder extends Seeder
{
    public function run(Restaurant $restaurant): void
    {
        LoyaltyProgram::withoutTenancy()->updateOrCreate(['restaurant_id' => $restaurant->id], [
            'is_enabled' => true,
            'earn_points_per_unit' => 10,
            'redeem_points' => 100,
            'redeem_value_minor' => 100,
            'max_redeem_percent' => 30,
            'min_redeem_points' => 500,
            'expiry_months' => 12,
            'tier_window_months' => 12,
        ]);

        $tiers = [
            [['en' => 'Bronze', 'ar' => 'برونزي'], 0, 10_000, null],
            [['en' => 'Silver', 'ar' => 'فضي'], 2_000, 11_000, ['en' => '10% more points on every order', 'ar' => 'نقاط أكثر بنسبة 10% على كل طلب']],
            [['en' => 'Gold', 'ar' => 'ذهبي'], 6_000, 12_500, ['en' => '25% more points and a birthday treat', 'ar' => 'نقاط أكثر بنسبة 25% وهدية عيد ميلاد']],
            [['en' => 'Platinum', 'ar' => 'بلاتيني'], 15_000, 15_000, ['en' => '50% more points and priority at the window', 'ar' => 'نقاط أكثر بنسبة 50% وأولوية عند النافذة']],
        ];

        foreach ($tiers as $sort => [$name, $threshold, $multiplier, $benefits]) {
            Tier::withoutTenancy()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'sort' => $sort],
                ['name' => $name, 'threshold_points' => $threshold, 'earn_multiplier_bp' => $multiplier, 'benefits' => $benefits],
            );
        }

        $cheapest = Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('is_available', true)->orderBy('price_minor')->first();
        $rewards = [
            [['en' => 'Free drink', 'ar' => 'مشروب مجاني'], RewardType::FreeItem, 1_500, $cheapest?->id, null],
            [['en' => '10 QAR off', 'ar' => 'خصم 10 ر.ق'], RewardType::FixedOff, 1_000, null, 1_000],
            [['en' => '20% off your order', 'ar' => 'خصم 20% على طلبك'], RewardType::PercentOff, 2_500, null, 2_000],
        ];

        foreach ($rewards as [$name, $type, $cost, $productId, $value]) {
            if ($type === RewardType::FreeItem && $productId === null) {
                continue;
            }

            Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', $name['en'])->exists()
                || Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => $name, 'type' => $type, 'points_cost' => $cost, 'product_id' => $productId, 'value' => $value, 'valid_days' => 30]);
        }

        $this->promotion($restaurant, ['name' => ['en' => 'Afternoon happy hour', 'ar' => 'الساعة السعيدة بعد الظهر'], 'type' => PromotionType::Percent, 'value_bp' => 1500, 'time_from' => '15:00', 'time_to' => '17:00']);
        $welcome = $this->promotion($restaurant, ['name' => ['en' => 'Welcome 10%', 'ar' => 'ترحيب 10%'], 'type' => PromotionType::Percent, 'value_bp' => 1000, 'requires_coupon' => true, 'per_customer_limit' => 1]);

        Coupon::withoutTenancy()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'code' => 'WELCOME10'],
            ['promotion_id' => $welcome->id, 'per_customer_limit' => 1],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function promotion(Restaurant $restaurant, array $attributes): Promotion
    {
        return Promotion::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', $attributes['name']['en'])->first()
            ?? Promotion::withoutTenancy()->create(['restaurant_id' => $restaurant->id, ...$attributes]);
    }
}
