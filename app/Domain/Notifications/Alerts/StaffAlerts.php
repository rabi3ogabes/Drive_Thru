<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Alerts;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Models\Branch;
use App\Domain\Staff\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends an operations alert to the people responsible for a branch: active
 * staff allowed to run it who cover that branch, and the owner. Restaurant-
 * wide alerts (no branch) go to the owner and staff who cover every branch.
 */
final class StaffAlerts
{
    /**
     * @param  array<string, string|int|array<string, string>>  $params
     */
    public function send(AlertKind $kind, int $restaurantId, ?Branch $branch, array $params, string $url): int
    {
        $recipients = $this->recipients($restaurantId, $branch);

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new OpsAlert($kind, $restaurantId, $params, $url));

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(int $restaurantId, ?Branch $branch): Collection
    {
        return Employee::withoutTenancy()
            ->with(['user', 'position', 'branches'])
            ->where('restaurant_id', $restaurantId)
            ->get()
            ->filter(fn (Employee $employee): bool => $employee->user->is_active
                && ($employee->is_owner || $employee->allows(RestaurantPermission::OperateBranch))
                && ($branch === null ? $employee->coversAllBranches() : $employee->coversBranch($branch)))
            ->map(fn (Employee $employee): User => $employee->user)
            ->unique('id')
            ->values();
    }
}
