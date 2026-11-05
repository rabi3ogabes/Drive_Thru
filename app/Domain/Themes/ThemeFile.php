<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\MenuTheme;
use JsonException;

/**
 * Theme files (.dtheme.json): a theme travels between restaurants and into
 * the admin library as a small JSON document. Parsing reads only the known
 * fields, checks every value, and never carries images or font data.
 * Fonts another restaurant uploaded are swapped for the template's.
 */
final class ThemeFile
{
    public const FORMAT = 'drive-thru-theme';

    public const VERSION = 1;

    public const MAX_BYTES = 20480;

    /**
     * @param  array{en: string, ar: string}  $name
     */
    public static function export(ThemeSpec $spec, array $name, string $base): string
    {
        $data = $spec->toArray();
        unset($data['cover']);

        return (string) json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'name' => $name,
            'base' => $base,
            'spec' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{spec: ThemeSpec, name: array{en: string, ar: string}, base: string, notices: list<string>}
     */
    public static function parse(string $contents, ?Restaurant $for = null): array
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw new ThemeRejected('too_large');
        }

        try {
            $data = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ThemeRejected('not_json');
        }

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT || ! is_int($data['version'] ?? null) || ! is_array($data['spec'] ?? null)) {
            throw new ThemeRejected('not_theme');
        }

        if ($data['version'] > self::VERSION) {
            throw new ThemeRejected('newer_version');
        }

        $baseKey = is_string($data['base'] ?? null) && preg_match('/^[a-z0-9-]{1,60}$/', $data['base']) === 1 ? $data['base'] : null;
        $template = ($baseKey !== null ? MenuTheme::query()->where('key', $baseKey)->first() : null) ?? MenuTheme::default();
        $base = $template->spec()->with(['cover' => null]);

        $spec = $data['spec'];
        unset($spec['cover']);
        $notices = [];

        foreach (ThemeSpec::FONT_ROLES as $role) {
            $font = $spec['fonts'][$role] ?? null;

            if (is_string($font) && str_starts_with($font, 'brand:') && ! BrandFonts::owns($for, $font)) {
                $spec['fonts'][$role] = $base->fonts[$role];
                $notices[] = __('themes.import.font_dropped', ['role' => __("themes.fonts.{$role}")]);
            }
        }

        $theme = ThemeSpec::fromArray($spec, $base, $for);
        $check = Contrast::check($theme);

        if (! $check['ok']) {
            throw new ThemeRejected('unreadable', ['ratio' => $check['errors'][0]['ratio']]);
        }

        return [
            'spec' => $theme,
            'name' => self::name(is_array($data['name'] ?? null) ? $data['name'] : [], $template),
            'base' => $template->key,
            'notices' => $notices,
        ];
    }

    /**
     * @param  array<mixed>  $name
     * @return array{en: string, ar: string}
     */
    private static function name(array $name, MenuTheme $template): array
    {
        $clean = fn (mixed $value): string => is_string($value) ? mb_substr(trim(strip_tags($value)), 0, 60) : '';
        $en = $clean($name['en'] ?? null);
        $ar = $clean($name['ar'] ?? null);

        return [
            'en' => $en !== '' ? $en : ($ar !== '' ? $ar : $template->getTranslation('name', 'en')),
            'ar' => $ar !== '' ? $ar : ($en !== '' ? $en : $template->getTranslation('name', 'ar')),
        ];
    }
}
