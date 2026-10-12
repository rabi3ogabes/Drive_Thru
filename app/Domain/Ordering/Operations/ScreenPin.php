<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Operations;

use App\Domain\Branches\Models\Device;
use App\Domain\Staff\Actions\SetEmployeePin;
use App\Domain\Staff\Models\Employee;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;

/**
 * Identifies the person behind a sensitive action on a shared branch screen
 * (refund, status override) from their PIN. Wrong PINs are limited per
 * screen, and every step-up is in the audit log under the person's name.
 */
final class ScreenPin
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 300;

    /** @throws OperationRefused */
    public function operator(Device $device, #[SensitiveParameter] ?string $pin, string $purpose): Operator
    {
        $key = 'screen-pin:'.$device->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw OperationRefused::because('pin_locked');
        }

        $pin = trim((string) $pin);
        $employee = preg_match('/^\d{4,6}$/', $pin) === 1
            ? Employee::withoutTenancy()
                ->with('user')
                ->where('restaurant_id', $device->restaurant_id)
                ->where('pin_hash', SetEmployeePin::hash($device->restaurant_id, $pin))
                ->first()
            : null;

        if ($employee === null || ! $employee->isActive() || ! $employee->user->is_active) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw OperationRefused::because('pin_invalid');
        }

        RateLimiter::clear($key);

        activity()
            ->performedOn($device)
            ->causedBy($employee->user)
            ->event('screen.pin_used')
            ->withProperties(['purpose' => $purpose, 'branch_id' => $device->branch_id])
            ->log('screen.pin_used');

        return Operator::staffOnDevice($employee->user, $device);
    }
}
