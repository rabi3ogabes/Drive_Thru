@php($items = $this->items())
<x-filament-widgets::widget>
    <x-filament::section :heading="__('dashboard.attention.title')" icon="heroicon-o-bolt">
        @if ($items === [])
            <div class="flex flex-col items-center py-8 text-center">
                <span class="grid size-12 place-items-center rounded-2xl bg-success-50 text-success-600 ring-1 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-check-badge" class="size-6" />
                </span>
                <p class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">{{ __('dashboard.attention.clear') }}</p>
                <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">{{ __('dashboard.attention.clear_hint') }}</p>
            </div>
        @else
            <ul role="list" class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="group flex items-center gap-3 py-3 outline-none">
                            <span @class([
                                'grid size-9 shrink-0 place-items-center rounded-xl ring-1',
                                'bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400' => $item['color'] === 'danger',
                                'bg-warning-50 text-warning-600 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400' => $item['color'] === 'warning',
                                'bg-info-50 text-info-600 ring-info-600/10 dark:bg-info-400/10 dark:text-info-400' => $item['color'] === 'info',
                            ])>
                                <x-filament::icon :icon="$item['icon']" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1 text-sm font-medium text-gray-800 group-hover:text-gray-950 group-focus-visible:underline dark:text-gray-200 dark:group-hover:text-white">{{ $item['title'] }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="flip-rtl size-4 text-gray-400 transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
