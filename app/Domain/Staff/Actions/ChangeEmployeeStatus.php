<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Staff\StaffGuard;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Suspends, reactivates or removes a team member. Suspending or removing
 * signs them out of every device straight away.
 */
final class ChangeEmployeeStatus
{
    public function __construct(private readonly StaffGuard $guard) {}

    /**
     * @throws AuthorizationException
     */
    public function suspend(Employee $employee, User $actor): void
    {
        $this->guard->ensureCanManage($actor, $employee);

        $employee->forceFill(['status' => EmployeeStatus::Suspended])->save();
        $this->signOut($employee);

        activity()->performedOn($employee)->causedBy($actor)->event('employee.suspended')->log('employee.suspended');
    }

    /**
     * @throws AuthorizationException
     */
    public function reactivate(Employee $employee, User $actor): void
    {
        $this->guard->ensureCanManage($actor, $employee);

        // Someone who never accepted their invitation goes back to "invited".
        $status = $employee->joined_at === null ? EmployeeStatus::Invited : EmployeeStatus::Active;
        $employee->forceFill(['status' => $status])->save();

        activity()->performedOn($employee)->causedBy($actor)->event('employee.reactivated')->log('employee.reactivated');
    }

    /**
     * @throws AuthorizationException
     */
    public function remove(Employee $employee, User $actor): void
    {
        $this->guard->ensureCanManage($actor, $employee);

        DB::transaction(function () use ($employee, $actor): void {
            activity()->performedOn($employee->restaurant)->causedBy($actor)->event('employee.removed')
                ->withProperties(['name' => $employee->user->name, 'email' => $employee->user->email])
                ->log('employee.removed');

            $this->signOut($employee);
            $employee->branches()->detach();
            $employee->delete();
        });
    }

    private function signOut(Employee $employee): void
    {
        // Database sessions: drop every session of this person. If they also
        // work for another restaurant they simply sign in again.
        DB::table((string) config('session.table', 'sessions'))->where('user_id', $employee->user_id)->delete();
    }
}
