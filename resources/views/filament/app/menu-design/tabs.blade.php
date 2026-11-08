{{-- Menu design: Theme (the look) and Layout (cards and what they show). --}}
<nav class="inline-flex rounded-xl bg-gray-100 p-1 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" aria-label="{{ __('menu.design.title') }}">
    @foreach (['theme' => ['heroicon-m-swatch', __('themes.tabs.theme')], 'layout' => ['heroicon-m-squares-2x2', __('themes.tabs.layout')]] as $key => [$icon, $label])
        <a
            href="{{ \App\Filament\App\Pages\MenuDesign::getUrl(['tab' => $key]) }}"
            @if ($tab === $key) aria-current="page" @endif
            @class([
                'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition',
                'bg-white text-gray-950 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-white dark:ring-white/10' => $tab === $key,
                'text-gray-600 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $tab !== $key,
            ])
        >
            <x-filament::icon :icon="$icon" class="size-4" />
            {{ $label }}
        </a>
    @endforeach
</nav>
