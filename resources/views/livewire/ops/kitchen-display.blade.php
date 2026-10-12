@php
    $titles = [
        'received' => __('ops.kitchen.columns.received'),
        'preparing' => __('ops.kitchen.columns.preparing'),
        'ready' => __('ops.kitchen.columns.ready'),
    ];
    $screenTitle = $station ? __('ops.screens.station', ['station' => $station->getLabel()]) : __('ops.screens.kitchen');
@endphp
<div wire:poll.20s>
    <x-ops.shell :branch="$branch" :title="$screenTitle" :device-id="$deviceId" screen="kitchen">
        <x-slot:tools>
            <dl class="hidden items-center gap-2 lg:flex">
                <div class="rounded-2xl bg-white/5 px-4 py-1.5 ring-1 ring-white/8">
                    <dt class="text-[0.6875rem] text-white/45">{{ __('ops.kitchen.done_today') }}</dt>
                    <dd class="text-lg font-semibold tabular-nums">{{ $doneToday }}</dd>
                </div>
                <div class="rounded-2xl bg-white/5 px-4 py-1.5 ring-1 ring-white/8">
                    <dt class="text-[0.6875rem] text-white/45">{{ __('ops.kitchen.average_prep') }}</dt>
                    <dd class="text-lg font-semibold tabular-nums">{{ $averagePrep !== null ? __('ops.minutes_short', ['count' => $averagePrep]) : '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-white/5 px-4 py-1.5 ring-1 ring-white/8">
                    <dt class="text-[0.6875rem] text-white/45">{{ __('ops.kitchen.quoted') }}</dt>
                    <dd class="text-lg font-semibold tabular-nums">{{ __('ops.minutes_short', ['count' => $load['estimate']]) }}</dd>
                </div>
            </dl>
        </x-slot:tools>

        {{-- Held orders: scheduled starts and guests on their way --}}
        @if ($held->isNotEmpty())
            <section class="flex gap-2 overflow-x-auto border-b border-white/6 px-4 py-3 sm:px-6 [scrollbar-width:none]" aria-label="{{ __('ops.kitchen.held') }}">
                <span class="flex shrink-0 items-center pe-1 text-sm font-medium text-white/50">{{ __('ops.kitchen.held') }}</span>
                @foreach ($held as $order)
                    <span wire:key="held-{{ $order->id }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full bg-white/6 px-4 text-sm ring-1 ring-white/10">
                        <x-filament::icon icon="heroicon-o-clock" class="size-4 text-ice-300" />
                        <span class="font-semibold tabular-nums">{{ $order->displayNumber() }}</span>
                        @if ($order->prepare_at)
                            <span class="text-white/65" x-data="{ at: {{ $order->prepare_at->getTimestampMs() }} }"
                                  x-text="(m => m <= 0 ? @js(__('ops.kitchen.starting')) : @js(__('ops.kitchen.prepare_in', ['minutes' => '__M__'])).replace('__M__', m))(Math.ceil((at - $store.clock.now) / 60000))"></span>
                        @else
                            <span class="text-white/65">{{ $order->on_the_way_at ? __('ops.badges.on_the_way') : __('ops.kitchen.waiting_guest') }}</span>
                        @endif
                    </span>
                @endforeach
            </section>
        @endif

        <div class="grid gap-4 p-4 sm:p-6 lg:grid-cols-3">
            @foreach ($columns as $key => $orders)
                <section aria-labelledby="col-{{ $key }}" class="min-w-0">
                    <header class="mb-3 flex items-center justify-between">
                        <h2 id="col-{{ $key }}" class="flex items-center gap-2 text-lg font-semibold">
                            <span @class(['size-2.5 rounded-full', 'bg-status-new' => $key === 'received', 'bg-status-progress' => $key === 'preparing', 'bg-status-ready' => $key === 'ready'])></span>
                            {{ $titles[$key] }}
                        </h2>
                        <span class="rounded-full bg-white/8 px-3 py-1 text-sm font-semibold tabular-nums">{{ $orders->count() }}</span>
                    </header>

                    @if ($orders->isEmpty())
                        <p class="rounded-3xl border border-dashed border-white/10 px-6 py-10 text-center text-white/40">{{ __('ops.kitchen.empty.'.$key) }}</p>
                    @endif

                    <div class="grid gap-3 2xl:grid-cols-2">
                        @foreach ($orders as $order)
                            @include('livewire.ops.partials.kitchen-card', ['order' => $order, 'column' => $key, 'isFresh' => in_array($order->id, $fresh, true)])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Recently cancelled, to re-enable a mistake --}}
        @if ($cancelled->isNotEmpty())
            <details class="mx-4 mb-6 rounded-3xl bg-white/[0.03] ring-1 ring-white/8 sm:mx-6">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-5 text-base text-white/70 [&::-webkit-details-marker]:hidden">
                    <span>{{ __('ops.kitchen.recently_cancelled', ['count' => $cancelled->count()]) }}</span>
                    <x-filament::icon icon="heroicon-o-chevron-down" class="size-5" />
                </summary>
                <ul class="divide-y divide-white/6 px-5 pb-3">
                    @foreach ($cancelled as $order)
                        <li wire:key="x-{{ $order->id }}" class="flex flex-wrap items-center gap-3 py-3">
                            <span class="text-lg font-semibold tabular-nums">{{ $order->displayNumber() }}</span>
                            <span class="min-w-0 flex-1 truncate text-white/55">{{ $order->cancel_reason }}</span>
                            <button type="button" x-on:click="$dispatch('ops-reason', { method: 'reEnable', args: [{{ $order->id }}], title: @js(__('ops.reason.enable_title', ['number' => $order->displayNumber()])), action: @js(__('ops.actions.re_enable')), tone: 'neutral', choices: @js(__('ops.reason.enable_choices')) })"
                                    class="min-h-14 rounded-2xl bg-white/8 px-5 font-semibold ring-1 ring-white/10 hover:bg-white/12">{{ __('ops.actions.re_enable') }}</button>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </x-ops.shell>

    @include('ops.partials.reason-sheet')
</div>
