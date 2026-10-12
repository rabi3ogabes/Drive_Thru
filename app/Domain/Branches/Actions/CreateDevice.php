<?php

declare(strict_types=1);

namespace App\Domain\Branches\Actions;

use App\Domain\Branches\Data\PairingCode;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Models\User;

/**
 * Registers a screen for a branch and returns its first one-time pairing code.
 */
final class CreateDevice
{
    public function __construct(private readonly IssuePairingCode $issue) {}

    public function handle(Branch $branch, DeviceKind $kind, string $name, ?int $stationId = null, ?User $actor = null): PairingCode
    {
        $device = Device::withoutTenancy()->create([
            'restaurant_id' => $branch->restaurant_id,
            'branch_id' => $branch->id,
            'station_id' => $kind === DeviceKind::Kitchen ? $stationId : null,
            'name' => $name,
            'kind' => $kind,
            'created_by' => $actor?->id,
        ]);

        return $this->issue->handle($device, $actor);
    }
}
