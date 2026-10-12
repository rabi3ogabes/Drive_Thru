<?php

declare(strict_types=1);

namespace App\Domain\Branches\Actions;

use App\Domain\Branches\Data\PairingCode;
use App\Domain\Branches\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Gives a screen a fresh 6-digit code that pairs it within 15 minutes. Any
 * previous code stops working. Re-pairing an already paired screen signs the
 * old one out once the new one pairs.
 */
final class IssuePairingCode
{
    public const VALID_MINUTES = 15;

    public function handle(Device $device, ?User $actor = null): PairingCode
    {
        return DB::transaction(function () use ($device, $actor): PairingCode {
            do {
                $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            } while (Device::withoutTenancy()->where('pairing_code_hash', Device::hash($code))->exists());

            $expiresAt = now()->addMinutes(self::VALID_MINUTES)->toImmutable();

            $device->forceFill([
                'pairing_code_hash' => Device::hash($code),
                'pairing_expires_at' => $expiresAt,
                'revoked_at' => null,
            ])->save();

            activity()->performedOn($device)->causedBy($actor)->event('device.code_issued')->log('device.code_issued');

            return new PairingCode($device, $code, $expiresAt);
        });
    }
}
