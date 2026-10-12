<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Http\JsonResponse;

/**
 * A web app manifest per restaurant, so "Add to Home Screen" installs the
 * restaurant's own app: its name, logo and colour.
 */
final class ManifestController
{
    public function __invoke(Restaurant $restaurant): JsonResponse
    {
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $restaurant->accent_color) === 1 ? $restaurant->accent_color : '#0f3b3a';
        $logo = $restaurant->getFilamentAvatarUrl();

        $icons = $logo !== null
            ? [['src' => $logo, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']]
            : [['src' => asset('favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']];

        return response()->json([
            'id' => route('public.restaurant', $restaurant, false),
            'name' => $restaurant->name,
            'short_name' => mb_substr($restaurant->name, 0, 12),
            'description' => __('public.meta.description', ['restaurant' => $restaurant->name]),
            'start_url' => route('public.restaurant', $restaurant, false).'?source=pwa',
            'scope' => route('public.restaurant', $restaurant, false).'/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f7f5f0',
            'theme_color' => $accent,
            'lang' => app()->getLocale(),
            'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'icons' => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
