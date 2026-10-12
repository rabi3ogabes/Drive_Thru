<meta name="theme-color" content="{{ $themeColor }}">
@if (filled(config('webpush.public_key')))
    <meta name="webpush-key" content="{{ config('webpush.public_key') }}">
@endif
