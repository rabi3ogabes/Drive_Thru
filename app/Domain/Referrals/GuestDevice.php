<?php

declare(strict_types=1);

namespace App\Domain\Referrals;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The browser a guest uses, known by a random first-party cookie set on
 * guest pages. Only its hash is stored (on sign-in sessions and referrals),
 * so two accounts on one phone can be told apart from two real friends.
 */
final class GuestDevice
{
    public const COOKIE = 'dtp_device';

    private const YEARS = 5;

    public function __construct(private readonly Request $request) {}

    /** Gives the browser its cookie on the first guest page it opens. */
    public function remember(): void
    {
        if ($this->id() === null) {
            Cookie::queue(Cookie::make(self::COOKIE, Str::random(40), 60 * 24 * 365 * self::YEARS));
        }
    }

    public function hash(): ?string
    {
        $id = $this->id() ?? Cookie::queued(self::COOKIE)?->getValue();

        return is_string($id) && $id !== '' ? hash_hmac('sha256', $id, (string) config('app.key')) : null;
    }

    private function id(): ?string
    {
        $id = $this->request->cookie(self::COOKIE);

        return is_string($id) && preg_match('/^[A-Za-z0-9]{40}$/', $id) === 1 ? $id : null;
    }
}
