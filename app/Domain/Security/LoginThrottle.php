<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Security\Enums\SecurityAlertType;
use App\Domain\Security\Events\PrincipalLocked;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brute-force protection for staff sign-in. Failures are counted per email
 * and per IP in a rolling window; crossing a limit locks that principal and
 * raises a security alert. Emails and IPs are stored only as keyed hashes.
 */
final class LoginThrottle
{
    public function lockedUntil(?string $email, ?string $ip): ?CarbonImmutable
    {
        $until = DB::table('login_security_states')
            ->whereIn('principal', array_filter([$this->emailKey($email), $this->ipKey($ip)]))
            ->where('locked_until', '>', now())
            ->max('locked_until');

        return $until === null ? null : CarbonImmutable::parse($until);
    }

    public function recordFailure(?string $email, ?string $ip, ?string $userAgent = null): void
    {
        $config = config('security.login');

        if ($emailKey = $this->emailKey($email)) {
            $count = $this->increment($emailKey, (int) $config['max_email_failures']);

            if ($count === (int) $config['max_email_failures']) {
                PrincipalLocked::dispatch(SecurityAlertType::AccountLocked, $this->maskEmail((string) $email), $this->hash((string) $ip), $count, $userAgent, $emailKey);
            }
        }

        if ($ipKey = $this->ipKey($ip)) {
            $count = $this->increment($ipKey, (int) $config['max_ip_failures']);

            if ($count === (int) $config['max_ip_failures']) {
                PrincipalLocked::dispatch(SecurityAlertType::IpLocked, null, $this->hash((string) $ip), $count, $userAgent, $ipKey);
            }
        }
    }

    public function clear(?string $email, ?string $ip): void
    {
        DB::table('login_security_states')
            ->whereIn('principal', array_filter([$this->emailKey($email), $this->ipKey($ip)]))
            ->delete();
    }

    /** Lifts a lock (and its failure count) by principal key, e.g. from a security alert. */
    public function unlock(string $principal): bool
    {
        return DB::table('login_security_states')->where('principal', $principal)->delete() > 0;
    }

    public function unlockEmail(string $email): bool
    {
        $key = $this->emailKey($email);

        return $key !== null && $this->unlock($key);
    }

    public function isLocked(string $principal): bool
    {
        return DB::table('login_security_states')->where('principal', $principal)->where('locked_until', '>', now())->exists();
    }

    private function increment(string $principal, int $limit): int
    {
        return DB::transaction(function () use ($principal, $limit): int {
            $now = CarbonImmutable::now();
            $windowStart = $now->subMinutes((int) config('security.login.window_minutes'));

            $row = DB::table('login_security_states')->where('principal', $principal)->lockForUpdate()->first();

            $count = ($row === null || CarbonImmutable::parse($row->window_started_at)->lt($windowStart))
                ? 1
                : (int) $row->failed_count + 1;

            DB::table('login_security_states')->updateOrInsert(
                ['principal' => $principal],
                [
                    'failed_count' => $count,
                    'window_started_at' => $count === 1 ? $now : $row->window_started_at,
                    'last_failed_at' => $now,
                    'locked_until' => $count >= $limit
                        ? $now->addMinutes((int) config('security.login.lock_minutes'))
                        : ($row->locked_until ?? null),
                ],
            );

            return $count;
        });
    }

    private function emailKey(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return $email === '' ? null : 'email:'.$this->hash($email);
    }

    private function ipKey(?string $ip): ?string
    {
        return blank($ip) ? null : 'ip:'.$this->hash((string) $ip);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    /** Keeps alerts useful without storing the full attempted address: "ah***@example.com". */
    private function maskEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::limit($local, 2, '').'***'.($domain !== '' ? '@'.$domain : '');
    }
}
