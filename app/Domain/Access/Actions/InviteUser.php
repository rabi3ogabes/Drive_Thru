<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Notifications\InvitationNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues a fresh one-time link for a person to choose their password. Any
 * earlier pending link for that person stops working. Returns the URL so it
 * can also be shared by WhatsApp.
 */
final class InviteUser
{
    public const VALID_DAYS = 7;

    public function handle(User $user, ?User $inviter = null, bool $sendEmail = true, ?string $context = null): string
    {
        $token = Str::random(64);

        DB::transaction(function () use ($user, $inviter, $token): void {
            UserInvitation::query()
                ->where('user_id', $user->id)
                ->pending()
                ->update(['revoked_at' => now()]);

            UserInvitation::create([
                'user_id' => $user->id,
                'invited_by' => $inviter?->id,
                'token_hash' => UserInvitation::hashToken($token),
                'expires_at' => now()->addDays(self::VALID_DAYS),
            ]);
        });

        $url = route('invitation.show', ['token' => $token]);

        if ($sendEmail) {
            $user->notify(new InvitationNotification($url, $inviter?->name, $context));
        }

        activity()->performedOn($user)->causedBy($inviter)->event('user.invited')->log('Invitation sent');

        return $url;
    }
}
