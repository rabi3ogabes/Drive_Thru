<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Billing\Enums\PlanFeature as F;
use App\Domain\Billing\Models\Plan;

/**
 * The launch plans (prices from the reference product, per branch per month).
 * Installed idempotently; once installed, prices are edited in the console.
 */
final class PlanCatalog
{
    public function install(): void
    {
        foreach ($this->plans() as $sort => $plan) {
            Plan::query()->firstOrCreate(['key' => $plan['key']], $plan + ['sort' => $sort]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'key' => 'starter',
                'name' => ['en' => 'Starter', 'ar' => 'البداية'],
                'description' => ['en' => 'Everything a single drive-thru needs to take orders online.', 'ar' => 'كل ما يحتاجه فرع درايف ثرو واحد لاستقبال الطلبات أونلاين.'],
                'price_minor' => 34900,
                'limits' => ['branches' => 2, 'employees' => 15],
                'features' => [F::ArrivalTracking],
                'trial_days' => 14,
            ],
            [
                'key' => 'growth',
                'name' => ['en' => 'Growth', 'ar' => 'النمو'],
                'description' => ['en' => 'Multi-branch control with loyalty, promotions and deeper reports.', 'ar' => 'تحكم متعدد الفروع مع الولاء والعروض وتقارير أعمق.'],
                'price_minor' => 64900,
                'limits' => ['branches' => 10, 'employees' => 80],
                'features' => [F::ArrivalTracking, F::ScheduledOrders, F::Loyalty, F::Promotions, F::Campaigns, F::KitchenStations, F::AdvancedReports],
                'trial_days' => 14,
                'is_highlighted' => true,
            ],
            [
                'key' => 'enterprise',
                'name' => ['en' => 'Enterprise', 'ar' => 'المؤسسات'],
                'description' => ['en' => 'Unlimited branches, integrations, AI and a dedicated team.', 'ar' => 'فروع غير محدودة وتكاملات وذكاء اصطناعي وفريق مخصص.'],
                'price_minor' => null,
                'limits' => ['branches' => null, 'employees' => null],
                'features' => F::cases(),
                'trial_days' => 0,
            ],
        ];
    }
}
