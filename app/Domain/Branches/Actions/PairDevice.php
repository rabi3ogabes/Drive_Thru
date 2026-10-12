<?php

declare(strict_types=1);

namespace App\Domain\Branches\Actions;

use App\Domain\Branches\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Exchanges a valid one-time code for the screen's long-lived token. The code
 * is burned immediately, so it works exactly once. Returns null for unknown
 * or expired codes (the caller rate-limits attempts).
 *
 * @phpstan-type Paired array{device: Device, token: string}
 */
final class PairDevice
{
    /**
     * @return ?array{device: Device, token: string}
     */
    public function handle(#[SensitiveParameter] string $code, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== 6) {
            return null;
        }

        return DB::transaction(function () use ($code, $ip, $userAgent): ?array {
            $device = Device::withoutTenancy()
                ->where('pairing_code_hash', Device::hash($code))
                ->lockForUpdate()
                ->first();

            if ($device === null || ! $device->isAwaitingPairing()) {
                return null;
            }

            $token = Str::random(64);

            $device->forceFill([
                'pairing_code_hash' => null,
                'pairing_expires_at' => null,
                'token_hash' => Device::hash($token),
                'paired_at' => now(),
                'last_seen_at' => now(),
                'last_ip' => $ip,
                'user_agent' => $userAgent !== null ? Str::limit($userAgent, 250, '') : null,
            ])->save();

            activity()->performedOn($device)->event('device.paired')->withProperties(['ip' => $ip])->log('device.paired');

            return ['device' => $device, 'token' => $token];
        });
    }
}
