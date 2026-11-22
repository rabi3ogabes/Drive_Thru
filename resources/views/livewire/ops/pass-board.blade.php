@php
    $tz = $restaurant->timezone;
    $clock = fn (?int $seconds): string => $seconds === null ? '–' : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    $stations = $snapshot['stations'] ?? [];
    $totals = $snapshot['totals'] ?? ['on_pass' => 0, 'late' => 0, 'ready_today' => 0, 'orders_today' => 0, 'avg_ticket_seconds' => null];

    // The selected order, with its items from every station it hangs at.
    $focus = null;
    foreach ($stations as $group) {
        foreach ($group['tickets'] as $ticket) {
            if ($ticket['order_id'] !== $selected) {
                continue;
            }
            $focus = $focus === null ? $ticket : array_merge($focus, ['items' => array_merge($focus['items'], $ticket['items'])]);
        }
    }
    $hasTickets = $totals['on_pass'] > 0;
@endphp
<div wire:poll.15s>
    <x-ops.shell :restaurant="$restaurant" :title="__('ops.screens.overview')" :channel="'restaurant.'.$restaurant->id.'.dashboard'" screen="overview">
        <x-slot:tools>
            @if ($branches->count() > 1)
                <nav class="hidden items-center gap-1 rounded-full bg-white/6 p-1 ring-1 ring-white/10 lg:flex" aria-label="{{ __('ops.pass.branches') }}">
                    @foreach ($branches as $option)
                        <button type="button" wire:click="selectBranch({{ $option->id }})" wire:key="branch-{{ $option->id }}"
                                @class(['min-h-10 rounded-full px-4 text-sm font-semibold transition', 'bg-ivory-50 text-ink-950' => $option->id === $branch?->id, 'text-white/65 hover:text-white' => $option->id !== $branch?->id])
                                aria-pressed="{{ $option->id === $branch?->id ? 'true' : 'false' }}">{{ $option->name }}</button>
                    @endforeach
                </nav>
            @endif
            <div class="hidden text-end xl:block">
                <p class="text-xs text-white/45">{{ __('ops.pass.orders_today') }}</p>
                <p class="text-lg font-semibold tabular-nums" dir="ltr">{{ $totals['orders_today'] }}</p>
            </div>
        </x-slot:tools>

        <div class="pass" x-data="passBoard()">
            @if ($branches->count() > 1)
                <nav class="flex gap-1 overflow-x-auto px-4 pt-4 lg:hidden" aria-label="{{ __('ops.pass.branches') }}">
                    @foreach ($branches as $option)
                        <button type="button" wire:click="selectBranch({{ $option->id }})" wire:key="branch-m-{{ $option->id }}"
                                @class(['min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold ring-1 transition', 'bg-ivory-50 text-ink-950 ring-ivory-50' => $option->id === $branch?->id, 'text-white/70 ring-white/12' => $option->id !== $branch?->id])>{{ $option->name }}</button>
                    @endforeach
                </nav>
            @endif

            {{-- The rail --}}
            <section class="pass-rail" aria-label="{{ __('ops.pass.rail') }}">
                @if ($closed)
                    <p class="pass-banner" role="status">
                        <span class="size-2 rounded-full bg-status-progress"></span>
                        {{ __('ops.pass.closed') }}@if ($opensAt) · {{ __('ops.pass.opens', ['time' => $opensAt->setTimezone($tz)->format('H:i')]) }}@endif
                    </p>
                @endif

                <div class="pass-rail-body">
                    <div class="pass-rail-scroll" x-ref="rail" x-on:keydown="nav($event)">
                        @if (! $hasTickets)
                            <p class="pass-empty">{{ __('ops.pass.empty') }}</p>
                        @else
                            @foreach ($stations as $group)
                                @continue($group['tickets'] === [])
                                <div class="pass-station" wire:key="station-{{ $group['kind'] }}">
                                    <p class="pass-station-label">
                                        <span class="pass-station-name">{{ $group['name'] }}</span>
                                        <span>{{ __('ops.pass.on', ['count' => $group['on']]) }}</span>
                                        @if ($group['avg_seconds'] !== null)<span>· {{ __('ops.pass.avg', ['time' => $clock($group['avg_seconds'])]) }}</span>@endif
                                        @if ($group['late'] > 0)<span class="text-status-delayed">· {{ __('ops.pass.late', ['count' => $group['late']]) }}</span>@endif
                                    </p>
                                    <ul class="pass-hooks" role="list">
                                        @foreach ($group['tickets'] as $ticket)
                                            @include('livewire.ops.partials.pass-ticket', ['ticket' => $ticket, 'kind' => $group['kind']])
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- The spike: what just went out --}}
                    <div class="pass-spike" aria-label="{{ __('ops.pass.done') }}">
                        <span class="pass-spike-pin" aria-hidden="true"></span>
                        <span class="pass-spike-stack" aria-hidden="true">
                            @foreach (array_slice($snapshot['done'] ?? [], 0, 3) as $i => $done)
                                <span class="pass-spike-slip" style="--i: {{ $i }}" wire:key="spike-{{ $done['order_id'] }}"></span>
                            @endforeach
                        </span>
                        <p class="pass-spike-count"><span dir="ltr">{{ $totals['ready_today'] }}</span> {{ __('ops.pass.done') }}</p>
                        <p class="pass-spike-numbers" dir="ltr">
                            @forelse (array_slice($snapshot['done'] ?? [], 0, 4) as $done)
                                <span wire:key="spike-n-{{ $done['order_id'] }}">{{ $done['number'] }}</span>
                            @empty
                                <span class="text-white/35" dir="auto">{{ __('ops.pass.done_empty') }}</span>
                            @endforelse
                        </p>
                    </div>
                </div>
                <div class="pass-rail-shelf" aria-hidden="true"></div>
            </section>

            {{-- The shelf --}}
            <section class="pass-shelf">
                <div class="pass-panel">
                    <p class="pass-panel-title">{{ __('ops.pass.on_pass') }}</p>
                    <p class="mt-2 flex items-baseline gap-2"><span class="text-6xl font-semibold tabular-nums" dir="ltr">{{ $totals['on_pass'] }}</span><span class="text-white/50">{{ __('ops.pass.tickets') }}</span></p>
                    @if ($totals['late'] > 0)<p class="mt-1 font-semibold text-status-delayed">{{ __('ops.pass.late', ['count' => $totals['late']]) }}</p>@endif
                    <p class="pass-panel-title mt-6">{{ __('ops.pass.avg_ticket') }}</p>
                    <p class="mt-1 text-5xl font-semibold tabular-nums" dir="ltr">{{ $clock($totals['avg_ticket_seconds']) }}</p>
                    <p class="mt-1 text-sm text-white/50">{{ __('ops.pass.ready_today', ['count' => $totals['ready_today']]) }}</p>
                </div>

                <div class="pass-panel">
                    <p class="pass-panel-title">{{ __('ops.pass.next') }}</p>
                    <ul class="mt-3 grid gap-2" role="list">
                        @forelse (array_slice($snapshot['next'] ?? [], 0, 5) as $row)
                            <li class="flex min-h-14 items-center gap-3 rounded-2xl bg-white/[0.04] px-3 ring-1 ring-white/6" wire:key="next-{{ $row['order_id'] }}">
                                <span class="w-14 shrink-0 text-xl font-semibold tabular-nums" dir="ltr">{{ $row['number'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-white/80">{{ __('ops.pass.items', ['count' => $row['items_count']]) }}</span>
                                    @if ($row['notes'])<span class="block truncate text-xs text-status-delayed" dir="auto">{{ $row['notes'] }}</span>@endif
                                </span>
                                @if ($row['status'] === 'scheduled')
                                    <span class="shrink-0 text-sm text-brass-200" dir="auto">{{ $row['due_at'] ? __('ops.pass.due', ['time' => \Carbon\CarbonImmutable::parse($row['due_at'])->setTimezone($tz)->format('H:i')]) : '' }}</span>
                                @elseif ($canStart)
                                    <button type="button" wire:click="start({{ $row['order_id'] }})" wire:loading.attr="disabled" wire:target="start({{ $row['order_id'] }})"
                                            class="min-h-11 shrink-0 rounded-full bg-ivory-50 px-4 text-sm font-semibold text-ink-950 transition active:scale-[0.97] disabled:opacity-60">{{ __('ops.pass.start') }}</button>
                                @endif
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-white/45">{{ __('ops.pass.next_empty') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="pass-panel">
                    <p class="pass-panel-title">{{ __('ops.pass.stations') }}</p>
                    <ul class="mt-4 grid gap-4" role="list">
                        @forelse ($stations as $group)
                            <li wire:key="load-{{ $group['kind'] }}">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-semibold">{{ $group['name'] }}</span>
                                    <span class="tabular-nums text-white/55">{{ __('ops.pass.on', ['count' => $group['on']]) }} · {{ $group['avg_seconds'] !== null ? __('ops.pass.avg', ['time' => $clock($group['avg_seconds'])]) : __('ops.pass.no_avg') }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/8">
                                    <div @class(['h-full rounded-full transition-[width] duration-700', 'bg-status-delayed' => $group['late'] > 0, 'bg-ivory-50/80' => $group['late'] === 0]) style="width: {{ min(100, (int) round($group['load'] * 100)) }}%"></div>
                                </div>
                                @if ($group['oldest_seconds'] !== null)<p @class(['mt-1 text-xs tabular-nums', 'text-status-delayed' => $group['late'] > 0, 'text-white/45' => $group['late'] === 0])>{{ __('ops.pass.oldest', ['time' => $clock($group['oldest_seconds'])]) }}</p>@endif
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-white/45">{{ __('ops.pass.empty') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="pass-panel" aria-live="polite">
                    <p class="pass-panel-title">{{ __('ops.pass.selected') }}</p>
                    @if ($focus)
                        <div class="mt-2 flex items-start justify-between gap-3">
                            <p class="text-4xl font-semibold tabular-nums" dir="ltr">{{ $focus['number'] }}</p>
                            <p @class(['text-3xl font-semibold tabular-nums', 'text-status-delayed' => $focus['late']]) dir="ltr" x-text="age(@js($focus['received_at']))">{{ $clock((int) $focus['age_seconds']) }}</p>
                        </div>
                        <p class="text-sm text-brass-200">{{ __('ops.pass.'.$focus['status']) }}@if ($focus['arrived']) · {{ __('ops.pass.car_here') }}@endif</p>
                        @if ($focus['car'] || $focus['lane'])<p class="mt-1 text-sm text-white/60" dir="auto">{{ $focus['car'] ?? $focus['lane'] }}</p>@endif
                        <ul class="pass-receipt mt-4 grid gap-1.5" role="list">
                            @foreach ($focus['items'] as $item)
                                <li dir="auto"><span dir="ltr">{{ $item['quantity'] }}×</span> {{ $item['name'] }}@foreach ($item['options'] as $option)<span class="block ps-6 text-white/50">{{ $option }}</span>@endforeach @if ($item['notes'])<span class="block ps-6 text-status-delayed">{{ $item['notes'] }}</span>@endif</li>
                            @endforeach
                        </ul>
                        @if ($focus['notes'])<p class="mt-3 rounded-xl bg-status-delayed/12 px-3 py-2 text-sm text-ice-50" dir="auto"><span class="font-semibold">{{ __('ops.pass.note') }}:</span> {{ $focus['notes'] }}</p>@endif
                    @else
                        <p class="mt-6 text-sm text-white/45">{{ __('ops.pass.select_hint') }}</p>
                    @endif
                </div>
            </section>
        </div>
    </x-ops.shell>
</div>
