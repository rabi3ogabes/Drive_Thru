<?php

declare(strict_types=1);

namespace App\Domain\Branches\Actions;

use App\Domain\Branches\Models\Device;
use App\Models\User;

/** Signs a screen out for good: its token and any pending code stop working. */
final class RevokeDevice
{
    public function handle(Device $device, ?User $actor = null): void
    {
        $device->forceFill([
            'token_hash' => null,
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'revoked_at' => now(),
        ])->save();

        activity()->performedOn($device)->causedBy($actor)->event('device.revoked')->log('device.revoked');
    }
}
