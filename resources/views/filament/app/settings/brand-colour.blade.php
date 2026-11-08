{{-- The brand colour now lives in the theme; this shows it and links there. --}}
<div class="flex items-center gap-3 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <span class="size-10 shrink-0 rounded-lg ring-1 ring-gray-950/10 dark:ring-white/20" style="background: {{ $colour }}" aria-hidden="true"></span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('settings.fields.accent_color') }} <span class="font-mono text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ strtoupper($colour) }}</span></p>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('themes.settings.moved') }}</p>
    </div>
    <x-filament::link :href="\App\Filament\App\Pages\MenuDesign::getUrl(['tab' => 'theme'])" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
        {{ __('themes.settings.open') }}
    </x-filament::link>
</div>
