@php
    use App\Support\Locales;
    $locale = app()->getLocale();
    $themeable = (bool) ($themeable ?? false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ Locales::direction($locale) }}" @class(['site-themeable' => $themeable])>
<head>
    <meta charset="utf-8">
    <script>document.documentElement.classList.add('js')</script>
    @if ($themeable)
        {{-- The visitor's saved choice, applied before the first paint (no flash). --}}
        <script>try { const t = localStorage.getItem('dtp.site.theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t } catch (e) {}</script>
        <meta name="color-scheme" content="light dark">
        <meta name="theme-color" content="#f7f5f0" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0c1114" media="(prefers-color-scheme: dark)">
        <link rel="canonical" href="{{ url()->current() }}">
    @else
        <meta name="theme-color" content="{{ $themeColor ?? '#f7f5f0' }}">
        <meta name="color-scheme" content="{{ $colorScheme ?? 'light' }}">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
