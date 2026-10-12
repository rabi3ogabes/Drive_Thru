<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\BranchHour;
use App\Domain\Branches\Models\Device;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * The owner's go-live checklist, computed from real data so it can never
 * drift: brand, first branch, hours, team, a paired screen and a printed QR.
 * Menu and test-order steps join when those modules ship.
 */
final class SetupChecklist
{
    public const STEPS = ['brand', 'branch', 'hours', 'team', 'screen', 'qr'];

    /**
     * @return array<string, bool> step => done, in order
     */
    public function steps(Restaurant $restaurant): array
    {
        $id = $restaurant->id;

        return [
            'brand' => $restaurant->logo_path !== null,
            'branch' => Branch::withoutTenancy()->where('restaurant_id', $id)->exists(),
            'hours' => BranchHour::withoutTenancy()->where('restaurant_id', $id)->exists(),
            'team' => Employee::withoutTenancy()->where('restaurant_id', $id)->where('is_owner', false)->exists(),
            'screen' => Device::withoutTenancy()->where('restaurant_id', $id)->whereNull('revoked_at')->whereNotNull('paired_at')->exists(),
            'qr' => isset($restaurant->settings['onboarding']['qr_downloaded_at']),
        ];
    }

    /** 0–100, rounded. */
    public function progress(Restaurant $restaurant): int
    {
        $steps = $this->steps($restaurant);

        return (int) round(count(array_filter($steps)) / count($steps) * 100);
    }

    public function isComplete(Restaurant $restaurant): bool
    {
        return ! in_array(false, $this->steps($restaurant), true);
    }

    public function isDismissed(Restaurant $restaurant): bool
    {
        return isset($restaurant->settings['onboarding']['checklist_dismissed_at']);
    }

    public function dismiss(Restaurant $restaurant): void
    {
        $settings = $restaurant->settings ?? [];
        $settings['onboarding']['checklist_dismissed_at'] = now()->toIso8601String();
        $restaurant->forceFill(['settings' => $settings])->save();
    }
}
