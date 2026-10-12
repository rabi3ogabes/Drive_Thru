<?php

declare(strict_types=1);

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatars rendered locally as inline SVG. Unlike the default
 * provider, no name ever leaves the platform and avatars work offline.
 * Each person gets a stable tone from the brand palette.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    /** @var list<array{0: string, 1: string}> Background and text pairs from the brand palette. */
    private const TONES = [
        ['#0f3b3a', '#d5b582'],
        ['#1c2436', '#cadff0'],
        ['#5e472f', '#f2e8d6'],
        ['#184c47', '#e4eff8'],
        ['#313a4f', '#e5d1ae'],
        ['#5a6d59', '#f7f5f0'],
    ];

    public function get(Model $record): string
    {
        $name = trim(Filament::getNameForDefaultAvatar($record));

        $initials = collect(preg_split('/\s+/u', $name) ?: [])
            ->map(fn (string $part): string => (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $part))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        [$background, $foreground] = self::TONES[crc32($name) % count(self::TONES)];

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="%s"/><text x="50%%" y="50%%" dy=".35em" text-anchor="middle" font-family="Geist Variable, IBM Plex Sans Arabic, system-ui, sans-serif" font-size="24" font-weight="600" fill="%s">%s</text></svg>',
            $background,
            $foreground,
            htmlspecialchars($initials !== '' ? $initials : '•', ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
