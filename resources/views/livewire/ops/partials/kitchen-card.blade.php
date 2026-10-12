@php
    use App\Domain\Ordering\Enums\OrderStatus;
    $start = ($order->received_at ?? $order->placed_at ?? $order->created_at)?->getTimestampMs() ?? 0;
    $readyAt = $order->ready_at?->getTimestampMs();
    $target = max(1, $order->prep_minutes);
    $vehicle = $order->vehicle;
    $items = $station ? $order->items->sortBy(fn ($i) => $this->madeAt($i, $station) ? 0 : 1) : $order->items;
    $stationOpen = $station ? $order->items->contains(fn ($i) => $this->madeAt($i, $station) && $i->done_at === null) : true;
@endphp
<article wire:key="k-{{ $order->id }}"
         x-data="{
            start: {{ $start }},
            target: {{ $target }},
            get elapsed() { return Math.max(0, Math.floor(($store.clock.now - this.start) / 1000)) },
            get clock() { const s = this.elapsed; return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0') },
            get level() { const r = this.elapsed / 60 / this.target; return r >= 1 ? 'late' : (r >= 0.75 ? 'soon' : 'ok') },
         }"
         @class([
            'relative flex flex-col rounded-3xl bg-white/[0.045] p-4 ring-1 ring-white/8 transition',
            'ops-glow-new' => $column === 'received',
            'ops-glow-progress' => $column === 'preparing',
         ])
         :class="{ 'ops-glow-overdue': @js($column !== 'ready') && level === 'late' }">

    <header class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-3xl font-semibold leading-none tracking-tight tabular-nums">{{ $order->displayNumber() }}</p>
            <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-sm text-white/60">
                @if ($order->lane)<span class="rounded-full bg-white/8 px-2 py-0.5">{{ $order->lane->name }}</span>@endif
                @if ($vehicle)<bdi class="truncate">{{ $vehicle['label'] ?? $vehicle['plate'] ?? '' }}</bdi>@endif
            </p>
        </div>

        @if ($column === 'ready')
            <div class="text-end">
                <p class="text-sm text-white/50">{{ __('ops.kitchen.ready_for') }}</p>
                <p class="text-2xl font-semibold tabular-nums" dir="ltr" x-data="{ at: {{ $readyAt ?? $start }} }" x-text="(s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'))(Math.max(0, Math.floor(($store.clock.now - at) / 1000)))"></p>
            </div>
        @else
            <div class="text-end">
                <p class="text-3xl font-semibold leading-none tabular-nums" dir="ltr" x-text="clock"
                   :class="{ 'text-white': level === 'ok', 'text-status-progress': level === 'soon', 'text-status-delayed': level === 'late' }">00:00</p>
                <p class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                   :class="{ 'bg-status-ready/15 text-sage-100': level === 'ok', 'bg-status-progress/15 text-brass-100': level === 'soon', 'bg-status-delayed/20 text-ice-50': level === 'late' }">
                    <svg x-show="level === 'ok'" class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg x-show="level === 'soon'" x-cloak class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M8 5v3.5l2 1.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <svg x-show="level === 'late'" x-cloak class="size-3.5" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 2.5 14 13H2L8 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 7v2.5M8 11.5v.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <span x-show="level === 'ok'">{{ __('ops.urgency.ok', ['minutes' => $target]) }}</span>
                    <span x-show="level === 'soon'" x-cloak>{{ __('ops.urgency.soon') }}</span>
                    <span x-show="level === 'late'" x-cloak>{{ __('ops.urgency.late') }}</span>
                </p>
            </div>
        @endif
    </header>

    {{-- Badges: arrival, on the way, new --}}
    @if ($order->arrived_at || $order->on_the_way_at || $isFresh || $order->scheduled_for)
        <div class="mt-3 flex flex-wrap gap-1.5">
            @if ($order->arrived_at)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-status-ready/18 px-3 py-1 text-sm font-semibold text-sage-100"><x-filament::icon icon="heroicon-o-map-pin" class="size-4" />{{ __('ops.badges.arrived') }}</span>
            @elseif ($order->on_the_way_at)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-ice-400/15 px-3 py-1 text-sm font-semibold text-ice-100"><x-filament::icon icon="heroicon-o-truck" class="size-4" />{{ __('ops.badges.on_the_way') }}</span>
            @endif
            @if ($order->scheduled_for)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/8 px-3 py-1 text-sm text-white/75"><x-filament::icon icon="heroicon-o-calendar" class="size-4" />{{ __('ops.badges.scheduled', ['time' => $order->scheduled_for->setTimezone($tz)->format('H:i')]) }}</span>
            @endif
            @if ($isFresh)
                <span class="inline-flex items-center rounded-full bg-status-new px-3 py-1 text-sm font-semibold text-white">{{ __('ops.badges.new') }}</span>
            @endif
        </div>
    @endif

    {{-- Items --}}
    <ul class="mt-3 space-y-2 border-t border-white/8 pt-3">
        @foreach ($items as $item)
            @php $mine = ! $station || $this->madeAt($item, $station); @endphp
            <li @class(['flex gap-3', 'opacity-35' => ! $mine, 'line-through decoration-white/40' => $item->done_at && $column !== 'ready'])>
                <span class="grid h-8 min-w-8 shrink-0 place-items-center rounded-lg bg-white/10 px-1.5 text-lg font-semibold tabular-nums">{{ $item->quantity }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-lg font-medium leading-snug">
                        {{ $item->localName() }}
                        @if ($station && ! $mine && $item->station)<span class="text-sm text-white/50">· {{ $item->station->getLabel() }}</span>@endif
                    </p>
                    @if ($labels = $item->optionLabels())
                        <p class="text-base leading-snug text-white/65">{{ implode(' · ', $labels) }}</p>
                    @endif
                    @if ($item->notes)
                        <p class="mt-1 rounded-lg bg-brass-400/15 px-2 py-1 text-base text-brass-100">“{{ $item->notes }}”</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($order->notes)
        <p class="mt-3 rounded-xl bg-brass-400/12 px-3 py-2 text-base text-brass-100 ring-1 ring-brass-300/20">
            <span class="font-semibold">{{ __('ops.kitchen.order_note') }}</span> {{ $order->notes }}
        </p>
    @endif

    {{-- Actions --}}
    @if ($column !== 'ready')
        <div class="mt-4 flex gap-2">
            @if ($station && ! $stationOpen)
                <p class="flex min-h-14 flex-1 items-center justify-center rounded-2xl bg-white/5 text-base text-white/55">{{ __('ops.kitchen.waiting_other_stations') }}</p>
            @else
                <button type="button"
                        wire:click="{{ $station ? 'stationDone' : 'advance' }}({{ $order->id }})" wire:loading.attr="disabled" wire:target="advance({{ $order->id }}), stationDone({{ $order->id }})"
                        @class(['flex min-h-14 flex-1 items-center justify-center gap-2 rounded-2xl text-lg font-semibold transition active:scale-[0.98] disabled:opacity-60',
                                'bg-white text-ink-950' => $column === 'received' && ! $station,
                                'bg-status-ready text-white' => $column === 'preparing' || $station])>
                    @if ($station)
                        {{ __('ops.actions.station_done', ['station' => $station->getLabel()]) }}
                    @elseif ($column === 'received')
                        {{ __('ops.actions.start') }}
                    @else
                        {{ __('ops.actions.ready') }}
                    @endif
                </button>
            @endif
            <button type="button" wire:click="printTicket({{ $order->id }}, 'kitchen')"
                    class="grid min-h-14 w-14 place-items-center rounded-2xl bg-white/6 text-white/70 ring-1 ring-white/10 hover:bg-white/12"
                    aria-label="{{ __('print.print_kitchen') }}" title="{{ __('print.print_kitchen') }}">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6.72 13.83c-.24.03-.48.06-.72.1m.72-.1a42.4 42.4 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.17c.24.03.48.06.72.1m-.72-.1L17.66 18m0 0 .23 2.5a1.13 1.13 0 0 1-1.12 1.23H7.23a1.13 1.13 0 0 1-1.12-1.23L6.34 18m11.32 0h1.09A2.25 2.25 0 0 0 21 15.75V9.46c0-1.08-.77-2.01-1.84-2.2a48.4 48.4 0 0 0-1.41-.21M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.46c0-1.08.77-2.01 1.84-2.2a48.4 48.4 0 0 1 1.41-.21m11.5 0a48.5 48.5 0 0 0-11.5 0m11.5 0V3.37c0-.62-.5-1.12-1.12-1.12H7.37c-.62 0-1.12.5-1.12 1.12v3.68M18 10.5h.01v.01H18V10.5Zm-3 0h.01v.01H15V10.5Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" x-on:click="$dispatch('ops-reason', { method: 'cancel', args: [{{ $order->id }}], title: @js(__('ops.reason.cancel_title', ['number' => $order->displayNumber()])), action: @js(__('ops.actions.cancel_order')), tone: 'danger', choices: @js(__('ops.reason.cancel_choices')) })"
                    class="grid min-h-14 w-14 place-items-center rounded-2xl bg-white/6 text-white/70 ring-1 ring-white/10 hover:bg-status-delayed/20 hover:text-white"
                    aria-label="{{ __('ops.actions.cancel_order') }}" title="{{ __('ops.actions.cancel_order') }}">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
            </button>
        </div>
    @endif
</article>
