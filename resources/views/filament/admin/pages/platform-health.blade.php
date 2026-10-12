<x-filament-panels::page>
    @php
        $providers = $this->providers();
        $tone = [
            'healthy' => ['dot' => 'bg-success-500', 'badge' => 'success'],
            'degraded' => ['dot' => 'bg-warning-500', 'badge' => 'warning'],
            'down' => ['dot' => 'bg-danger-500', 'badge' => 'danger'],
            'idle' => ['dot' => 'bg-gray-300 dark:bg-gray-600', 'badge' => 'gray'],
        ];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div role="tablist" aria-label="{{ __('platform.health.period') }}" class="inline-flex rounded-full bg-gray-950/5 p-1 dark:bg-white/5">
            @foreach (['1h', '24h', '7d'] as $option)
                <button type="button" role="tab" aria-selected="{{ $period === $option ? 'true' : 'false' }}" wire:click="setPeriod('{{ $option }}')"
                    @class([
                        'min-h-9 rounded-full px-4 text-sm font-medium transition-colors duration-300 ease-[cubic-bezier(0.32,0.72,0,1)]',
                        'bg-white text-gray-950 shadow-sm dark:bg-white/10 dark:text-white' => $period === $option,
                        'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $period !== $option,
                    ])>{{ __('platform.health.periods.'.$option) }}</button>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400" wire:loading.remove>{{ __('platform.health.refreshes') }}</p>
        <x-filament::loading-indicator wire:loading class="size-5 text-gray-400" />
    </div>

    <div wire:poll.60s class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($providers as $row)
            <section class="rounded-[1.75rem] bg-gray-950/[0.03] p-1.5 ring-1 ring-gray-950/5 dark:bg-white/[0.03] dark:ring-white/10" wire:key="provider-{{ $row['provider'] }}">
                <div class="h-full rounded-[calc(1.75rem-0.375rem)] bg-white p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)] dark:bg-gray-900">
                    <header class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2.5 font-semibold text-gray-950 dark:text-white">
                            <span class="relative flex size-2.5">
                                @if ($row['status'] === 'down')<span class="absolute inline-flex size-full animate-ping rounded-full bg-danger-400 opacity-60"></span>@endif
                                <span class="relative inline-flex size-2.5 rounded-full {{ $tone[$row['status']]['dot'] }}"></span>
                            </span>
                            {{ __('platform.health.providers.'.$row['provider']) }}
                        </h2>
                        <x-filament::badge :color="$tone[$row['status']]['badge']" size="sm">{{ __('platform.health.status.'.$row['status']) }}</x-filament::badge>
                    </header>

                    @if ($row['calls'] === 0)
                        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">{{ __('platform.health.no_calls') }}</p>
                    @else
                        <dl class="mt-5 grid grid-cols-3 gap-3 text-center">
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('platform.health.success') }}</dt>
                                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format((float) $row['success_rate'], 1) }}%</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('platform.health.calls') }}</dt>
                                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($row['calls']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('platform.health.speed') }}</dt>
                                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $row['avg_ms'] }}<span class="text-sm font-normal text-gray-500"> ms</span></dd>
                            </div>
                        </dl>
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            {{ trans_choice('platform.health.restaurants', $row['restaurants'], ['count' => $row['restaurants']]) }}
                            · {{ __('platform.health.last_call', ['time' => $row['last_call_at']?->diffForHumans()]) }}
                        </p>
                        @if ($row['last_error'])
                            <p class="mt-3 rounded-2xl bg-danger-50 px-3 py-2 text-xs text-danger-700 ring-1 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-300">
                                <span class="font-medium">{{ __('platform.health.last_error', ['time' => $row['last_error_at']?->diffForHumans()]) }}</span>
                                <span class="mt-0.5 block break-words font-mono" dir="ltr">{{ \Illuminate\Support\Str::limit($row['last_error'], 140) }}</span>
                            </p>
                        @endif
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    <x-filament::section :heading="__('platform.health.setup_heading')" :description="__('platform.health.setup_hint')">
        <ul role="list" class="-my-2 divide-y divide-gray-950/5 dark:divide-white/5">
            @foreach ($this->setup() as $item)
                <li class="flex items-center gap-3 py-3">
                    <x-filament::icon :icon="$item['ok'] ? 'heroicon-s-check-circle' : 'heroicon-s-exclamation-circle'" @class(['size-5 shrink-0', 'text-success-600 dark:text-success-400' => $item['ok'], 'text-warning-600 dark:text-warning-400' => ! $item['ok']]) />
                    <span class="flex-1 text-sm text-gray-950 dark:text-white">{{ __('platform.health.setup.'.$item['key'].'.label') }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('platform.health.setup.'.$item['key'].'.'.($item['ok'] ? 'ok' : 'missing'), ['detail' => $item['detail']]) }}</span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
