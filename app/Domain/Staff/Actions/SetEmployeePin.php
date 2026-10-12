<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Staff\Models\Employee;
use App\Domain\Staff\StaffGuard;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Sets or clears the 4–6 digit PIN someone uses on shared branch screens.
 * PINs are unique within a restaurant (so a PIN alone identifies a person on
 * a paired screen) and stored as a keyed hash, never in plain text.
 */
final class SetEmployeePin
{
    public function __construct(private readonly StaffGuard $guard) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(Employee $employee, #[SensitiveParameter] ?string $pin, User $actor): void
    {
        if ($actor->id !== $employee->user_id) {
            $this->guard->ensureCanManage($actor, $employee);
        }

        if ($pin === null || $pin === '') {
            $employee->forceFill(['pin_hash' => null])->save();
            activity()->performedOn($employee)->causedBy($actor)->event('employee.pin_cleared')->log('employee.pin_cleared');

            return;
        }

        self::validate($pin);

        $hash = self::hash($employee->restaurant_id, $pin);

        $taken = Employee::withoutTenancy()
            ->where('restaurant_id', $employee->restaurant_id)
            ->where('pin_hash', $hash)
            ->whereKeyNot($employee->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['pin' => __('staff.pin.errors.taken')]);
        }

        $employee->forceFill(['pin_hash' => $hash])->save();
        activity()->performedOn($employee)->causedBy($actor)->event('employee.pin_set')->log('employee.pin_set');
    }

    public static function hash(int $restaurantId, #[SensitiveParameter] string $pin): string
    {
        return hash_hmac('sha256', $restaurantId.'|'.$pin, (string) config('app.key'));
    }

    /**
     * @throws ValidationException
     */
    public static function validate(#[SensitiveParameter] string $pin): void
    {
        if (preg_match('/^\d{4,6}$/', $pin) !== 1) {
            throw ValidationException::withMessages(['pin' => __('staff.pin.errors.format')]);
        }

        $digits = array_map('intval', str_split($pin));
        $steps = array_values(array_unique(array_map(
            fn (int $i): int => $digits[$i + 1] - $digits[$i],
            range(0, count($digits) - 2),
        )));

        // 1111, 1234, 9876 … are the first PINs anyone tries.
        if (count($steps) === 1 && in_array($steps[0], [-1, 0, 1], true)) {
            throw ValidationException::withMessages(['pin' => __('staff.pin.errors.weak')]);
        }
    }
}
