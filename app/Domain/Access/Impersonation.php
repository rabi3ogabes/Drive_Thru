<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * "View as owner": platform support opens a restaurant workspace as its owner
 * to help them. Requires a reason, is fully audited, never targets platform
 * staff, and keeps the platform user one click away from returning.
 */
final class Impersonation
{
    private const SESSION_KEY = 'impersonation';

    /** True while this class itself is switching users, so sign-in listeners can ignore it. */
    private static bool $switching = false;

    public static function isSwitching(): bool
    {
        return self::$switching;
    }

    public function start(User $impersonator, Restaurant $restaurant, string $reason): User
    {
        if (! $impersonator->hasPlatformPermission(PlatformPermission::ImpersonateOwners)) {
            throw new AuthorizationException;
        }

        if ($this->isActive()) {
            throw new DomainException(__('clients.impersonation.errors.already_active'));
        }

        $owner = $restaurant->owner();

        if ($owner === null || ! $owner->is_active) {
            throw new DomainException(__('clients.impersonation.errors.no_owner'));
        }

        if ($owner->isPlatformStaff()) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw new DomainException(__('clients.impersonation.errors.reason'));
        }

        session()->put(self::SESSION_KEY, [
            'impersonator_id' => $impersonator->id,
            'restaurant_id' => $restaurant->id,
            'reason' => $reason,
            'started_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        activity()
            ->performedOn($restaurant)
            ->causedBy($impersonator)
            ->event('impersonation.started')
            ->withProperties(['owner_id' => $owner->id, 'reason' => $reason])
            ->log('Viewed workspace as owner');

        $this->switchTo($owner);

        return $owner;
    }

    public function stop(): ?User
    {
        $data = session()->get(self::SESSION_KEY);

        if (! is_array($data)) {
            return null;
        }

        $impersonator = is_numeric($data['impersonator_id'] ?? null) ? User::query()->find((int) $data['impersonator_id']) : null;
        $restaurant = is_numeric($data['restaurant_id'] ?? null) ? Restaurant::query()->find((int) $data['restaurant_id']) : null;

        session()->forget(self::SESSION_KEY);

        $startedAt = CarbonImmutable::parse((string) ($data['started_at'] ?? 'now'));

        $log = activity()
            ->causedBy($impersonator)
            ->event('impersonation.ended')
            ->withProperties(['minutes' => (int) round($startedAt->diffInMinutes(CarbonImmutable::now(), true))]);

        if ($restaurant !== null) {
            $log->performedOn($restaurant);
        }

        $log->log('Returned to platform');

        if ($impersonator === null || ! $impersonator->is_active) {
            Auth::guard('web')->logout();

            return null;
        }

        $this->switchTo($impersonator);

        return $impersonator;
    }

    public function isActive(): bool
    {
        return is_array(session()->get(self::SESSION_KEY));
    }

    public function impersonator(): ?User
    {
        $id = session()->get(self::SESSION_KEY.'.impersonator_id');

        return is_numeric($id) ? User::query()->find((int) $id) : null;
    }

    public function restaurantId(): ?int
    {
        $id = session()->get(self::SESSION_KEY.'.restaurant_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function reason(): ?string
    {
        $reason = session()->get(self::SESSION_KEY.'.reason');

        return is_string($reason) ? $reason : null;
    }

    private function switchTo(User $user): void
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');

        self::$switching = true;

        try {
            $guard->login($user);
        } finally {
            self::$switching = false;
        }

        // AuthenticateSession pins the session to the password hash of the signed-in user.
        session()->put('password_hash_web', $guard->hashPasswordForCookie((string) $user->getAuthPassword()));
        session()->regenerate();
    }
}
