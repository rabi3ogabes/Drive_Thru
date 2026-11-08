@props(['restaurant', 'title' => null])
@php
    use App\Support\Locales;
    $locale = app()->getLocale();
    $themePreview = \App\Domain\Themes\ThemePreview::draft($restaurant, request()->query('theme_preview'));
    $theme = $themePreview ?? \App\Domain\Themes\ThemeResolver::for($restaurant);
    $themePreload = \App\Domain\Themes\ThemeCss::preload($theme, $locale);
    $accent = $theme->colors['brand'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ Locales::direction($locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $accent }}">
    @if (filled(config('webpush.public_key')))
        <meta name="webpush-key" content="{{ config('webpush.public_key') }}">
    @endif
    <meta name="color-scheme" content="{{ $theme->mode }}">
    @if ($themePreview)
        <meta name="robots" content="noindex">
    @endif
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $restaurant->name }}">
    <title>{{ $title ? $title.' · '.$restaurant->name : $restaurant->name }}</title>
    <meta name="description" content="{{ __('public.meta.description', ['restaurant' => $restaurant->name]) }}">
    <meta property="og:title" content="{{ $restaurant->name }}">
    <meta property="og:site_name" content="{{ $restaurant->name }}">
    <link rel="manifest" href="{{ route('customer.manifest', $restaurant) }}">
    <link rel="icon" href="{{ $restaurant->getFilamentAvatarUrl() ?? asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ $restaurant->getFilamentAvatarUrl() ?? asset('favicon.svg') }}">
    @if ($themePreload)
        <link rel="preload" href="{{ $themePreload }}" as="font" type="font/woff2" crossorigin>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! \App\Domain\Themes\ThemeCss::for($restaurant, $themePreview) !!}</style>
    @if ($themePreview)
        {{-- Preview: browse this page only; links out and forms are off, so nothing can be ordered. --}}
        <style>a[href]:not([href^="#"]), form, [type="submit"] { pointer-events: none; }</style>
    @endif
</head>
<body class="min-h-dvh bg-ivory-50 text-ink-900 antialiased [-webkit-tap-highlight-color:transparent]" data-theme-mode="{{ $theme->mode }}" @if ($themePreview) data-theme-preview @endif>
    @if ($themePreview)
        <div class="pointer-events-none fixed inset-x-0 top-0 z-50 flex justify-center pt-[max(0.5rem,env(safe-area-inset-top))]">
            <span class="rounded-full bg-[var(--brand)] px-3 py-1 text-[0.6875rem] font-semibold text-on-brand shadow-lg">{{ __('themes.preview.ribbon') }}</span>
        </div>
    @endif
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">
        {{ __('app.skip_to_content') }}
    </a>

    {{ $slot }}

    <script>
        // Offline fallback and instant repeat visits; never caches personal pages.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</body>
</html>
