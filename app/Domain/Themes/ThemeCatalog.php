<?php

declare(strict_types=1);

namespace App\Domain\Themes;

use App\Domain\Themes\Models\MenuTheme;

/**
 * The built-in theme templates. install() adds the ones that are missing and
 * never touches a template the super admin has already edited.
 * "ivory-teal" is today's look, so existing restaurants don't change.
 */
final class ThemeCatalog
{
    public function install(): void
    {
        $hasDefault = MenuTheme::query()->where('is_default', true)->exists();

        foreach ($this->templates() as $sort => $template) {
            if (MenuTheme::query()->where('key', $template['key'])->exists()) {
                continue;
            }

            MenuTheme::query()->create($template + [
                'sort' => $sort,
                'is_built_in' => true,
                'is_active' => true,
                'is_default' => ! $hasDefault && $template['key'] === 'ivory-teal',
            ]);
        }
    }

    /**
     * @return list<array{key: string, name: array{en: string, ar: string}, description: array{en: string, ar: string}, spec: array<string, mixed>}>
     */
    public function templates(): array
    {
        return [
            $this->template('ivory-teal', ['en' => 'Ivory & Teal', 'ar' => 'العاجي والفيروزي'], ['en' => 'Calm ivory, deep teal and a touch of brass. The classic look.', 'ar' => 'عاجي هادئ وفيروزي عميق ولمسة نحاسية. الإطلالة الكلاسيكية.'],
                'light', ['brand' => '#0f3b3a', 'accent' => '#b08d57', 'background' => '#fcfbf8', 'text' => '#111827'],
                'fraunces+alexandria', 'geist+ibm-plex-sans-arabic', 'rounded'),
            $this->template('midnight-brass', ['en' => 'Midnight Brass', 'ar' => 'نحاس منتصف الليل'], ['en' => 'Near-black surfaces lit by warm brass. Made for evening menus.', 'ar' => 'أسطح شبه سوداء يضيئها نحاس دافئ. مصمم لقوائم المساء.'],
                'dark', ['brand' => '#d4af6a', 'accent' => '#c9a45c', 'background' => '#0e0f12', 'text' => '#f4efe6'],
                'cormorant-garamond+el-messiri', 'manrope+ibm-plex-sans-arabic', 'soft'),
            $this->template('desert-sand', ['en' => 'Desert Sand', 'ar' => 'رمال الصحراء'], ['en' => 'Warm sand and terracotta with an elegant serif.', 'ar' => 'رمال دافئة وطين محروق مع خط أنيق.'],
                'light', ['brand' => '#a4472d', 'accent' => '#c98b4b', 'background' => '#f6efe4', 'text' => '#2b1d14'],
                'playfair-display+amiri', 'plus-jakarta-sans+tajawal', 'soft'),
            $this->template('olive-grove', ['en' => 'Olive Grove', 'ar' => 'بستان الزيتون'], ['en' => 'Olive green on cream. Fresh, natural and friendly.', 'ar' => 'أخضر زيتوني على كريمي. منعش وطبيعي وودود.'],
                'light', ['brand' => '#4b5d2a', 'accent' => '#b08a3e', 'background' => '#f8f6ef', 'text' => '#1d2316'],
                'manrope+readex-pro', 'manrope+readex-pro', 'rounded'),
            $this->template('rose-cafe', ['en' => 'Rose Café', 'ar' => 'مقهى الورد'], ['en' => 'Soft rose and cocoa, for cafés and sweets.', 'ar' => 'وردي ناعم وكاكاو، للمقاهي والحلويات.'],
                'light', ['brand' => '#9c3d54', 'accent' => '#c98b91', 'background' => '#fbf3f1', 'text' => '#3a2624'],
                'dm-serif-display+tajawal', 'plus-jakarta-sans+tajawal', 'rounded'),
            $this->template('mono-bold', ['en' => 'Mono Bold', 'ar' => 'أبيض وأسود جريء'], ['en' => 'Black and white with one strong red. Sharp and confident.', 'ar' => 'أبيض وأسود مع أحمر واحد قوي. حاد وواثق.'],
                'light', ['brand' => '#d7262e', 'accent' => '#1f1f1f', 'background' => '#ffffff', 'text' => '#0b0b0b'],
                'outfit+cairo', 'outfit+cairo', 'sharp', ['card_style' => 'list']),
        ];
    }

    /**
     * @param  array{en: string, ar: string}  $name
     * @param  array{en: string, ar: string}  $description
     * @param  array{brand: string, accent: string, background: string, text: string}  $colors
     * @param  array<string, mixed>  $layout
     * @return array{key: string, name: array{en: string, ar: string}, description: array{en: string, ar: string}, spec: array<string, mixed>}
     */
    private function template(string $key, array $name, array $description, string $mode, array $colors, string $heading, string $body, string $corners, array $layout = []): array
    {
        $spec = ThemeSpec::fromArray([
            'mode' => $mode,
            'colors' => $colors,
            'fonts' => ['heading' => $heading, 'body' => $body],
            'corners' => $corners,
            'layout' => $layout,
            'cover' => null,
        ])->toArray();
        unset($spec['cover']);

        return ['key' => $key, 'name' => $name, 'description' => $description, 'spec' => $spec];
    }
}
