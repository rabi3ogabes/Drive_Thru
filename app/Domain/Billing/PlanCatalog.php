<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Billing\Enums\PlanFeature as F;
use App\Domain\Billing\Models\Plan;

/**
 * The plan tiers: branch and staff limits plus add-ons. They carry no fee by
 * default (the services are priced); Enterprise is priced per client.
 * Installed idempotently; once installed, they are edited in the console.
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
                'description' => ['en' => 'For one or two branches getting started.', 'ar' => 'لفرع أو فرعين في البداية.'],
                'price_minor' => 0,
                'limits' => ['branches' => 2, 'employees' => 15],
                'features' => [],
                'trial_days' => 0,
            ],
            [
                'key' => 'growth',
                'name' => ['en' => 'Growth', 'ar' => 'النمو'],
                'description' => ['en' => 'Up to 10 branches, with deeper reports.', 'ar' => 'حتى 10 فروع، مع تقارير أعمق.'],
                'price_minor' => 0,
                'limits' => ['branches' => 10, 'employees' => 80],
                'features' => [F::AdvancedReports],
                'trial_days' => 0,
                'is_highlighted' => true,
            ],
            [
                'key' => 'enterprise',
                'name' => ['en' => 'Enterprise', 'ar' => 'المؤسسات'],
                'description' => ['en' => 'Unlimited branches, API, AI, custom domain and a dedicated team.', 'ar' => 'فروع غير محدودة وواجهة برمجية وذكاء اصطناعي ونطاق خاص وفريق مخصص.'],
                'price_minor' => null,
                'limits' => ['branches' => null, 'employees' => null],
                'features' => F::addOns(),
                'trial_days' => 0,
            ],
        ];
    }
}
