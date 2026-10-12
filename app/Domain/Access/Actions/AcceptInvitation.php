<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\UserInvitation;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * Completes an invitation: sets the chosen password, verifies the email
 * (they proved they own it by opening the link) and activates any pending
 * restaurant memberships.
 */
final class AcceptInvitation
{
    public function find(string $token): ?UserInvitation
    {
        $invitation = UserInvitation::query()
            ->with('user')
            ->where('token_hash', UserInvitation::hashToken($token))
            ->first();

        return $invitation?->isUsable() ? $invitation : null;
    }

    public function handle(UserInvitation $invitation, #[SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($invitation, $password): User {
            $user = $invitation->user;

            $user->forceFill([
                'password' => $password,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_active' => true,
            ])->save();

            $invitation->update(['accepted_at' => now()]);

            Employee::withoutTenancy()
                ->where('user_id', $user->id)
                ->where('status', EmployeeStatus::Invited)
                ->update(['status' => EmployeeStatus::Active, 'joined_at' => now()]);

            activity()->performedOn($user)->causedBy($user)->event('user.invitation_accepted')->log('Invitation accepted');

            return $user;
        });
    }
}
