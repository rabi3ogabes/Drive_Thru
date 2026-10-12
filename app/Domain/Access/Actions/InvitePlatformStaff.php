<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\PlatformRole;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a member to the platform team with a role, and sends their invitation.
 */
final class InvitePlatformStaff
{
    public function __construct(private readonly InviteUser $invite) {}

    /**
     * @return array{user: User, url: string}
     */
    public function handle(string $name, string $email, PlatformRole $role, User $inviter, ?string $locale = null): array
    {
        $email = Str::lower(trim($email));

        if (User::query()->where('email', $email)->exists()) {
            throw new DomainException(__('platform.errors.email_taken'));
        }

        $user = DB::transaction(function () use ($name, $email, $role, $locale): User {
            $user = User::create([
                'name' => trim($name),
                'email' => $email,
                'password' => Str::password(40),
                'locale' => $locale,
            ]);
            $user->forceFill(['platform_role_id' => $role->id])->save();

            return $user;
        });

        $url = $this->invite->handle($user, $inviter, context: 'platform');

        return ['user' => $user, 'url' => $url];
    }
}
