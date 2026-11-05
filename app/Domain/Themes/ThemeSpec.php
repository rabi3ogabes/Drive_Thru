<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Menu\MenuLayout;
use App\Domain\Themes\Exceptions\ThemeRejected;

/**
 * Everything a theme decides: light or dark, four colours, a heading and a
 * body font pair, corner style, the menu layout and an optional cover.
 * Templates, a restaurant's overrides and theme files all use this shape.
 */
final readonly class ThemeSpec
{
    public const MODES = ['light', 'dark'];

    public const CORNERS = ['soft', 'rounded', 'sharp'];

    public const COLORS = ['brand', 'accent', 'background', 'text'];

    public const FONT_ROLES = ['heading', 'body'];

    /** A catalogue pair ("latin+arabic") or an uploaded font ("brand:12"). */
    public const FONT_PATTERN = '/^(?:[a-z0-9-]{2,40}\+[a-z0-9-]{2,40}|brand:[1-9][0-9]{0,9})$/';

    /**
     * @param  array{brand: string, accent: string, background: string, text: string}  $colors
     * @param  array{heading: string, body: string}  $fonts
     */
    public function __construct(
        public string $mode,
        public array $colors,
        public array $fonts,
        public string $corners,
        public MenuLayout $layout,
        public ?string $cover = null,
    ) {}

    /**
     * A full spec, or a partial one laid over $base. Every value is checked;
     * anything that doesn't fit is refused rather than guessed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?self $base = null): self
    {
        $mode = $data['mode'] ?? $base?->mode;
        if (! in_array($mode, self::MODES, true)) {
            throw new ThemeRejected('bad_value', ['field' => 'mode']);
        }

        $corners = $data['corners'] ?? $base?->corners;
        if (! in_array($corners, self::CORNERS, true)) {
            throw new ThemeRejected('bad_value', ['field' => 'corners']);
        }

        $colorData = is_array($data['colors'] ?? null) ? $data['colors'] : [];
        $colors = [];
        foreach (self::COLORS as $key) {
            $value = $colorData[$key] ?? $base?->colors[$key] ?? null;
            if (! is_string($value)) {
                throw new ThemeRejected('bad_color', ['value' => $key]);
            }
            $colors[$key] = Palette::normalize($value);
        }

        $fontData = is_array($data['fonts'] ?? null) ? $data['fonts'] : [];
        $fonts = [];
        foreach (self::FONT_ROLES as $role) {
            $value = $fontData[$role] ?? $base?->fonts[$role] ?? null;
            if (! is_string($value) || preg_match(self::FONT_PATTERN, $value) !== 1) {
                throw new ThemeRejected('unknown_font', ['role' => $role]);
            }
            if (! str_starts_with($value, 'brand:')) {
                FontCatalog::pair($value, $role);
            }
            $fonts[$role] = $value;
        }

        $layout = MenuLayout::fromArray(array_merge($base?->layout->toArray() ?? [], is_array($data['layout'] ?? null) ? $data['layout'] : []));

        $cover = array_key_exists('cover', $data) ? $data['cover'] : $base?->cover;
        if ($cover !== null && (! is_string($cover) || strlen($cover) > 255 || str_contains($cover, '..'))) {
            throw new ThemeRejected('bad_value', ['field' => 'cover']);
        }

        return new self(
            $mode,
            ['brand' => $colors['brand'], 'accent' => $colors['accent'], 'background' => $colors['background'], 'text' => $colors['text']],
            ['heading' => $fonts['heading'], 'body' => $fonts['body']],
            $corners,
            $layout,
            $cover,
        );
    }

    /**
     * @return array{mode: string, colors: array<string, string>, fonts: array<string, string>, corners: string, layout: array<string, mixed>, cover: ?string}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'colors' => $this->colors,
            'fonts' => $this->fonts,
            'corners' => $this->corners,
            'layout' => $this->layout->toArray(),
            'cover' => $this->cover,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray($overrides, $this);
    }

    /**
     * Only what differs from $base: what a restaurant stores on top of its template.
     *
     * @return array<string, mixed>
     */
    public function diff(self $base): array
    {
        $mine = $this->toArray();
        $theirs = $base->toArray();
        $diff = [];

        foreach ($mine as $key => $value) {
            if (is_array($value)) {
                $changed = array_diff_assoc(array_map(self::scalar(...), $value), array_map(self::scalar(...), (array) $theirs[$key]));
                if ($changed !== []) {
                    $diff[$key] = array_intersect_key($value, $changed);
                }
            } elseif ($value !== $theirs[$key]) {
                $diff[$key] = $value;
            }
        }

        return $diff;
    }

    private static function scalar(mixed $value): string
    {
        return var_export($value, true);
    }
}
