@php
    $restaurant = \App\Filament\App\Workspace::restaurant();
    $items = $this->links();
@endphp
<x-filament-panels::page>
    @if (\App\Filament\App\Resources\QrCampaigns\QrCampaignResource::canAccess())
        <a href="{{ \App\Filament\App\Resources\QrCampaigns\QrCampaignResource::getUrl() }}"
           class="group flex items-center gap-4 rounded-xl bg-primary-50 px-4 py-3.5 ring-1 ring-primary-600/10 transition hover:bg-primary-100 dark:bg-primary-500/10 dark:ring-primary-400/20 dark:hover:bg-primary-500/15">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-primary-600 ring-1 ring-primary-600/10 dark:bg-white/5 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-chart-bar" class="size-5" />
            </span>
            <span class="min-w-0 flex-1 text-sm text-gray-700 dark:text-gray-200">{{ __('qr.links_callout') }}</span>
            <span class="flex shrink-0 items-center gap-1 text-sm font-semibold text-primary-700 dark:text-primary-400">
                {{ __('qr.links_callout_cta') }}
                <x-filament::icon icon="heroicon-m-arrow-right" class="size-4 transition group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" />
            </span>
        </a>
    @endif

    @if (count($items) === 0)
        <x-filament::section>
            <div class="py-10 text-center">
                <x-filament::icon icon="heroicon-o-qr-code" class="mx-auto size-10 text-gray-400" />
                <p class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('links.empty.heading') }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('links.empty.description') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @foreach ($items as $item)
                <x-filament::section wire:key="{{ $item['key'] }}">
                    <div class="flex items-start gap-4" x-data="{ copied: false }">
                        <div class="w-24 shrink-0 rounded-2xl bg-white p-1.5 ring-1 ring-gray-950/5 dark:ring-white/10 [&_svg]:h-auto [&_svg]:w-full" aria-hidden="true">
                            {!! $item['qr'] !!}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $item['name'] }}</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</p>
                            <p class="mt-2 truncate text-sm font-medium text-primary-700 dark:text-primary-300" dir="ltr">{{ $item['display'] }}</p>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <x-filament::button size="xs" color="gray" icon="heroicon-m-clipboard-document"
                                    x-on:click="navigator.clipboard?.writeText(@js($item['url'])); copied = true; setTimeout(() => copied = false, 1800)">
                                    <span x-show="! copied">{{ __('links.copy') }}</span>
                                    <span x-show="copied" x-cloak>{{ __('links.copied') }}</span>
                                </x-filament::button>
                                <x-filament::button size="xs" color="gray" icon="heroicon-m-arrow-top-right-on-square" tag="a" :href="$item['url']" target="_blank">
                                    {{ __('links.open') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 border-t border-gray-950/5 pt-4 dark:border-white/10">
                        @foreach (['pdf' => __('links.download.poster'), 'png' => 'PNG', 'svg' => 'SVG'] as $format => $label)
                            <x-filament::button size="sm" :color="$format === 'pdf' ? 'primary' : 'gray'" :outlined="$format !== 'pdf'" icon="heroicon-m-arrow-down-tray" tag="a" :spa-mode="false"
                                :href="route('qr.download', array_filter(['restaurant' => $restaurant->slug, 'format' => $format, 'branch' => $item['branch'], 'kind' => $item['kind'] === 'stamps' ? 'stamps' : null]))">
                                {{ $label }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-information-circle" class="size-5 shrink-0" />
            {{ __('links.footer') }}
        </p>
    @endif
</x-filament-panels::page>
