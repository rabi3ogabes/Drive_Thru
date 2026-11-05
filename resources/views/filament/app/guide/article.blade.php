{{-- One guide article. $entry comes from Guide::entry(); $compact hides the summary (the side panel shows it as its description) and the open button (the reader is already on that page). --}}
@php
    $compact ??= false;
    $billing = ! $entry['available'] && \App\Filament\App\Pages\Billing::canAccess() ? \App\Filament\App\Pages\Billing::getUrl() : null;
@endphp
<div class="space-y-6 text-sm leading-relaxed">
    @unless ($compact)
        <p class="text-gray-600 dark:text-gray-300">{{ $entry['summary'] }}</p>
    @endunless

    @if (filled($entry['when']))
        <div class="flex gap-3">
            <x-filament::icon icon="heroicon-o-clock" class="mt-0.5 size-5 shrink-0 text-primary-600 dark:text-primary-400" />
            <div>
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('guide.ui.when') }}</p>
                <p class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $entry['when'] }}</p>
            </div>
        </div>
    @endif

    @if ($entry['actions'] !== [])
        <div>
            <p class="mb-2 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ __('guide.ui.actions') }}</p>
            <dl class="divide-y divide-gray-950/5 overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-white/[0.02] dark:ring-white/10">
                @foreach ($entry['actions'] as $label => $text)
                    <div @class([
                        'grid gap-1.5 px-4 py-3',
                        'sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)] sm:gap-5' => ! $compact,
                    ])>
                        <dt>
                            <span class="inline-flex max-w-full rounded-md bg-gray-50 px-2 py-0.5 text-[0.8125rem] font-medium text-gray-950 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/10">{{ $label }}</span>
                        </dt>
                        <dd class="text-gray-600 dark:text-gray-300">{{ $text }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @if ($entry['tips'] !== [])
        <div class="rounded-xl bg-primary-50/70 px-4 py-3.5 ring-1 ring-primary-600/10 dark:bg-primary-400/5 dark:ring-primary-400/15">
            <p class="flex items-center gap-2 text-xs font-semibold text-primary-700 dark:text-primary-300">
                <x-filament::icon icon="heroicon-m-light-bulb" class="size-4" />
                {{ __('guide.ui.tips') }}
            </p>
            <ul class="mt-2 space-y-1.5 text-gray-700 dark:text-gray-200">
                @foreach ($entry['tips'] as $tip)
                    <li class="flex gap-2">
                        <span class="mt-2 size-1 shrink-0 rounded-full bg-primary-500" aria-hidden="true"></span>
                        <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-950/5 pt-4 dark:border-white/10">
        <p class="flex min-w-0 items-center gap-2 text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-key" class="size-4 shrink-0" />
            <span>{{ __('guide.ui.who') }}: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $entry['who'] }}</span></span>
        </p>

        @if (! $entry['available'])
            <span class="flex items-center gap-2">
                <x-filament::badge color="warning" icon="heroicon-m-lock-closed">{{ __('guide.ui.not_on_plan') }}</x-filament::badge>
                @if ($billing)
                    <x-filament::link :href="$billing" size="sm">{{ __('guide.ui.see_billing') }}</x-filament::link>
                @endif
            </span>
        @elseif (! $compact && $entry['url'])
            <x-filament::button tag="a" size="sm" :href="$entry['url']" :target="$entry['external'] ? '_blank' : null" icon="heroicon-m-arrow-up-right" icon-position="after" :spa-mode="$entry['external'] ? false : null" class="rtl:[&_svg]:-scale-x-100">
                {{ __('guide.ui.open') }}
            </x-filament::button>
        @endif
    </div>
</div>
