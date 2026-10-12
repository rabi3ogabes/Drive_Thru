@php
    use App\Support\Locales;
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ Locales::direction($locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $themeColor ?? '#f7f5f0' }}">
    <meta name="color-scheme" content="{{ $colorScheme ?? 'light' }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @isset($description)
        <meta name="description" content="{{ $description }}">
        <meta property="og:description" content="{{ $description }}">
    @endisset
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_QA' : 'en_US' }}">
    @foreach (Locales::codes() as $code)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ url()->current() }}?lang={{ $code }}">
    @endforeach
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-dvh antialiased {{ $bodyClass ?? '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">
        {{ __('app.skip_to_content') }}
    </a>
    {{ $slot }}
</body>
</html>
