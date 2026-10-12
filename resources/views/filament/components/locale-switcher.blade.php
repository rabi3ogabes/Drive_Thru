@php
    use App\Support\Locales;
    $current = app()->getLocale();
    $next = Locales::other($current);
@endphp
<a
    href="{{ route('locale.switch', $next) }}"
    hreflang="{{ $next }}"
    lang="{{ $next }}"
    title="{{ __('app.switch_language_to', ['language' => Locales::native($next)]) }}"
    class="inline-flex h-9 items-center gap-2 rounded-full bg-white/70 px-3.5 text-sm font-medium text-ink-700 shadow-[var(--shadow-hairline)] backdrop-blur transition hover:bg-white hover:text-ink-950 dark:bg-white/5 dark:text-ink-200 dark:hover:bg-white/10 dark:hover:text-white"
>
    <x-filament::icon icon="heroicon-o-language" class="size-4 opacity-70" />
    <span>{{ Locales::native($next) }}</span>
</a>
