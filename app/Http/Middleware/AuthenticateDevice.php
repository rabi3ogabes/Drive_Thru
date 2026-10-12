<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Branches\Models\Device;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a paired branch screen in by its long-lived token cookie. The device
 * runs inside its restaurant's tenant context. Unknown or revoked screens go
 * back to the pairing page (or get 401 for background calls).
 */
final class AuthenticateDevice
{
    public const COOKIE = 'dtp_screen';

    /** Write last_seen_at at most this often, to keep heartbeats cheap. */
    private const TOUCH_EVERY_SECONDS = 30;

    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = self::resolve($request);

        if ($device === null) {
            if ($request->expectsJson()) {
                return response()->json(['paired' => false], 401);
            }

            return redirect()->route('screen.pair')
                ->withCookie(cookie()->forget(self::COOKIE))
                ->with('screen_status', $request->hasCookie(self::COOKIE) ? 'disconnected' : null);
        }

        $this->tenant->set($device->branch->restaurant);
        $request->attributes->set('device', $device);

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subSeconds(self::TOUCH_EVERY_SECONDS))) {
            $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        }

        return $next($request);
    }

    public static function resolve(Request $request): ?Device
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || strlen($token) !== 64) {
            return null;
        }

        $device = Device::withoutTenancy()
            ->with(['branch.restaurant', 'station'])
            ->where('token_hash', Device::hash($token))
            ->whereNull('revoked_at')
            ->first();

        return $device !== null && $device->branch !== null && $device->branch->restaurant !== null ? $device : null;
    }
}
