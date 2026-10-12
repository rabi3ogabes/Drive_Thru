@vite('resources/js/live.js')
<x-filament-widgets::widget>
    <div x-data="{ live: 'connecting' }"
         data-live-channel="platform.dashboard"
         data-live-auth="{{ url('/broadcasting/auth') }}"
         x-on:live-update.debounce.400ms="$wire.$refresh()"
         x-on:live-status="live = $event.detail.state"
         class="flex flex-wrap items-center gap-x-8 gap-y-3 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="flex items-center gap-2 text-sm font-semibold" role="status">
            <span class="relative flex size-2.5">
                <span x-show="live === 'live'" class="absolute inline-flex size-full animate-ping rounded-full bg-success-500 opacity-60"></span>
                <span class="relative inline-flex size-2.5 rounded-full" :class="live === 'live' ? 'bg-success-500' : (live === 'offline' ? 'bg-danger-500' : 'bg-gray-400')"></span>
            </span>
            <span x-show="live === 'live'" class="text-success-700 dark:text-success-400">{{ __('dashboard.live.connected') }}</span>
            <span x-show="live === 'offline'" x-cloak class="text-danger-700 dark:text-danger-400">{{ __('dashboard.live.offline') }}</span>
            <span x-show="live === 'connecting'" x-cloak class="text-gray-500">{{ __('dashboard.live.connecting') }}</span>
        </p>
        <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
            <div class="flex items-baseline gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('dashboard.live.orders_24h') }}</dt><dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $ordersToday }}</dd></div>
            <div class="flex items-baseline gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('dashboard.live.in_progress') }}</dt><dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $inProgress }}</dd></div>
            <div class="flex items-baseline gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('dashboard.live.restaurants') }}</dt><dd class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $restaurants }}</dd></div>
            <div class="flex items-baseline gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('dashboard.live.last_order') }}</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $last ? \Illuminate\Support\Carbon::parse($last)->diffForHumans() : '—' }}</dd></div>
        </dl>
    </div>
</x-filament-widgets::widget>
