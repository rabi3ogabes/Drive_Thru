@props(['restaurant', 'title' => null])
@php
    use App\Support\Locales;
    $locale = app()->getLocale();
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $restaurant->accent_color) === 1 ? $restaurant->accent_color : '#0f3b3a';
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
    <meta name="color-scheme" content="light">
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>:root { --brand: {{ $accent }}; }</style>
</head>
<body class="min-h-dvh bg-ivory-50 text-ink-900 antialiased [-webkit-tap-highlight-color:transparent]">
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
