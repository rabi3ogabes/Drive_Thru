<?php

declare(strict_types=1);

namespace App\Domain\Growth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Keeps bots and sales pitches out of the "request access" form without a
 * captcha (no third-party requests): the form carries an encrypted time it
 * was shown, real people take a few seconds to fill it in, and SEO / marketing
 * pitches and lookalikes of our own domain are recognised. Blocked requests
 * are dropped silently, so a bot learns nothing.
 */
final class LeadSpamGuard
{
    /** Nobody types a restaurant's details this fast. */
    public const MIN_SECONDS = 3;

    /** Phrases from the pitches the form actually received (lower case). */
    private const PITCHES = [
        'seo', 'search index', 'search engine', 'search results', 'google search', 'rank higher', 'ranking',
        'first page', '1st page', 'backlink', 'web traffic', 'website traffic', 'website’s visibility', "website's visibility",
        'digital marketing', 'explainer video', 'promotional video', 'video to advertise', 'instagram report',
    ];

    public function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * Why this request should be dropped, or null to let it through.
     *
     * @param  array<string, mixed>  $input
     */
    public function reason(array $input): ?string
    {
        $shownAt = $this->shownAt($input['form_started'] ?? null);

        if ($shownAt === null) {
            return 'token';
        }

        if (now()->getTimestamp() - $shownAt < self::MIN_SECONDS) {
            return 'too_fast';
        }

        $domain = Str::lower(Str::after((string) ($input['email'] ?? ''), '@'));
        if (str_contains($domain, 'dhawq') && $domain !== 'dhawq.net') {
            return 'lookalike';
        }

        $text = Str::lower(implode(' ', array_map('strval', [$input['restaurant_name'] ?? '', $input['contact_name'] ?? '', $input['message'] ?? ''])));
        foreach (self::PITCHES as $phrase) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u', $text) === 1) {
                return 'pitch';
            }
        }

        return null;
    }

    private function shownAt(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $value = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
