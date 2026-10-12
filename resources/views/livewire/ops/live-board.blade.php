@php
    use App\Domain\Ordering\Enums\OrderStatus;
    use App\Domain\Ordering\Enums\PaymentStatus;
    use App\Domain\Ordering\Models\Order;
    use App\Support\Money;
    $currency = $branch->restaurant->currency;
    $vs = fn (int $now, int $then): ?int => $then > 0 ? (int) round(($now - $then) / $then * 100) : null;
    $change = $vs($pulse['sales_today'], $pulse['sales_yesterday']);
    $statusTone = [
        'pending_payment' => 'bg-white/8 text-white/70',
        'scheduled' => 'bg-ice-400/15 text-ice-100',
        'received' => 'bg-status-new/20 text-ice-50',
        'preparing' => 'bg-status-progress/18 text-brass-100',
        'ready' => 'bg-status-ready/18 text-sage-100',
        'picked_up' => 'bg-white/8 text-white/60',
        'cancelled' => 'bg-white/6 text-white/45',
    ];
    $kpis = [
        ['new', __('ops.board.kpi.new'), $pulse['new'], 'text-status-new'],
        ['preparing', __('ops.board.kpi.preparing'), $pulse['preparing'], 'text-status-progress'],
        ['ready', __('ops.board.kpi.ready'), $pulse['ready'], 'text-status-ready'],
        ['waiting', __('ops.board.kpi.waiting'), $pulse['arrived_waiting'], 'text-ice-200'],
        ['delayed', __('ops.board.kpi.delayed'), $pulse['delayed'], $pulse['delayed'] > 0 ? 'text-status-delayed' : 'text-white'],
        ['orders', __('ops.board.kpi.orders_today'), $pulse['orders_today'], 'text-white'],
    ];
    $nextLabel = fn (Order $o): ?string => match ($o->status) {
        OrderStatus::Received => __('ops.actions.start'),
        OrderStatus::Preparing => __('ops.actions.ready'),
        OrderStatus::Ready => $o->payment_status === PaymentStatus::Paid ? __('ops.actions.hand_over') : null,
        default => null,
    };
@endphp
<div wire:poll.20s>
    <x-ops.shell :branch="$branch" :title="__('ops.screens.live')" :device-id="$deviceId" screen="live">
        <x-slot:tools>
            <div class="hidden rounded-2xl bg-white/6 p-1 ring-1 ring-white/10 md:flex" role="group" aria-label="{{ __('ops.board.view') }}">
                @foreach (['list' => __('ops.board.list'), 'box' => __('ops.board.box')] as $key => $label)
                    <button type="button" wire:click="$set('view', '{{ $key }}')" @class(['min-h-12 rounded-xl px-4 text-sm font-semibold', 'bg-white text-ink-950' => $view === $key, 'text-white/65' => $view !== $key]) aria-pressed="{{ $view === $key ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </x-slot:tools>

        {{-- KPI strip --}}
        <section class="grid grid-cols-2 gap-3 px-4 pt-4 sm:grid-cols-4 sm:px-6 xl:grid-cols-8" aria-label="{{ __('ops.board.kpis') }}">
            @foreach ($kpis as [$key, $label, $value, $tone])
                <div class="rounded-3xl bg-white/[0.045] px-5 py-4 ring-1 ring-white/8">
                    <p class="text-sm text-white/50">{{ $label }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
            <div class="rounded-3xl bg-white/[0.045] px-5 py-4 ring-1 ring-white/8">
                <p class="text-sm text-white/50">{{ __('ops.board.kpi.sales_today') }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums" dir="ltr">{{ Money::format($pulse['sales_today'], $currency) }}</p>
                @if ($change !== null)
                    <p @class(['text-xs font-semibold tabular-nums', 'text-status-ready' => $change >= 0, 'text-status-delayed' => $change < 0])>{{ __('ops.board.vs_yesterday', ['change' => ($change >= 0 ? '+' : '').$change.'%']) }}</p>
                @endif
            </div>
            <div class="rounded-3xl bg-white/[0.045] px-5 py-4 ring-1 ring-white/8">
                <p class="text-sm text-white/50">{{ __('ops.kitchen.average_prep') }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">{{ $pulse['average_prep'] !== null ? __('ops.minutes_short', ['count' => $pulse['average_prep']]) : '—' }}</p>
            </div>
        </section>

        {{-- Ticker --}}
        @if ($open->isNotEmpty())
            <div class="mx-4 mt-4 overflow-hidden rounded-2xl bg-white/[0.03] py-2.5 ring-1 ring-white/6 sm:mx-6" aria-hidden="true">
                <div class="flex w-max gap-8 whitespace-nowrap ps-6 text-sm text-white/70 motion-safe:animate-[ops-ticker_40s_linear_infinite] rtl:motion-safe:[animation-direction:reverse]">
                    @foreach ([1, 2] as $copy)
                        @foreach ($open as $order)
                            <span class="inline-flex items-center gap-2">
                                <span @class(['size-2 rounded-full', 'bg-status-new' => $order->status === OrderStatus::Received, 'bg-status-progress' => $order->status === OrderStatus::Preparing, 'bg-status-ready' => $order->status === OrderStatus::Ready, 'bg-white/40' => ! $order->status->isActive()])></span>
                                <span class="font-semibold tabular-nums text-white">{{ $order->displayNumber() }}</span>
                                {{ $order->status->getLabel() }} · {{ $order->customer?->firstName() ?? '—' }}
                                · <span class="tabular-nums">{{ __('ops.minutes_short', ['count' => (int) ($order->received_at ?? $order->placed_at)?->diffInMinutes(now())]) }}</span>
                            </span>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Filters --}}
        <nav class="flex gap-2 overflow-x-auto px-4 pt-4 sm:px-6 [scrollbar-width:none]" aria-label="{{ __('ops.board.filters') }}">
            @foreach (\App\Livewire\Ops\LiveBoard::FILTERS as $key)
                <button type="button" wire:click="$set('filter', '{{ $key }}')"
                        @class(['min-h-12 shrink-0 rounded-full px-5 text-base font-medium ring-1 transition', 'bg-white text-ink-950 ring-white' => $filter === $key, 'bg-white/5 text-white/70 ring-white/10 hover:bg-white/10' => $filter !== $key])
                        aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                    {{ __('ops.board.filter.'.$key) }}
                    @if ($key === 'delayed' && $pulse['delayed'] > 0)<span class="ms-1 rounded-full bg-status-delayed px-2 text-xs text-white tabular-nums">{{ $pulse['delayed'] }}</span>@endif
                </button>
            @endforeach
        </nav>

        {{-- Live orders --}}
        @unless (in_array($filter, ['completed', 'cancelled'], true))
            <section class="px-4 pt-4 sm:px-6" aria-label="{{ __('ops.board.live_orders') }}">
                @if ($rows->isEmpty())
                    <p class="rounded-3xl border border-dashed border-white/10 px-6 py-12 text-center text-lg text-white/40">{{ __('ops.board.empty') }}</p>
                @elseif ($view === 'box')
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($rows as $order)
                            <button type="button" wire:key="box-{{ $order->id }}" wire:click="select({{ $order->id }})"
                                    @class(['rounded-3xl bg-white/[0.045] p-4 text-start ring-1 ring-white/8 transition hover:bg-white/[0.07]',
                                            'ops-glow-new' => $order->status === OrderStatus::Received,
                                            'ops-glow-progress' => $order->status === OrderStatus::Preparing,
                                            'ops-glow-overdue' => in_array($order->id, $delayedIds, true)])>
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-2xl font-semibold tabular-nums">{{ $order->displayNumber() }}</span>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone[$order->status->value] }}">{{ $order->status->getLabel() }}</span>
                                </div>
                                <p class="mt-2 truncate text-white/70">{{ $order->customer?->firstName() ?? '—' }} · <bdi>{{ $order->vehicle['label'] ?? '—' }}</bdi></p>
                                <p class="mt-1 flex items-center justify-between text-sm text-white/55">
                                    <span dir="ltr" class="tabular-nums">{{ Money::format($order->total_minor, $currency) }}</span>
                                    @if ($order->arrived_at)<span class="font-semibold text-sage-100">{{ __('ops.badges.arrived') }}</span>@endif
                                </p>
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="relative overflow-x-auto rounded-3xl ring-1 ring-white/8">
                        <table class="w-full min-w-[56rem] text-start text-base">
                            <thead class="bg-white/[0.04] text-sm text-white/50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.order') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.customer') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.vehicle') }}</th>
                                    <th class="px-4 py-3 text-end font-medium">{{ __('ops.board.col.value') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.payment') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.kitchen') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.elapsed') }}</th>
                                    <th class="px-4 py-3 text-start font-medium">{{ __('ops.board.col.eta') }}</th>
                                    <th class="px-4 py-3"><span class="sr-only">{{ __('ops.board.col.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/6">
                                @foreach ($rows as $order)
                                    @php $next = $nextLabel($order); @endphp
                                    <tr wire:key="row-{{ $order->id }}" @class(['transition hover:bg-white/[0.03]', 'bg-status-new/[0.06]' => in_array($order->id, $fresh, true), 'bg-status-delayed/[0.07]' => in_array($order->id, $delayedIds, true)])>
                                        <td class="px-4 py-3">
                                            <button type="button" wire:click="select({{ $order->id }})" class="text-start">
                                                <span class="block text-lg font-semibold tabular-nums">{{ $order->displayNumber() }}</span>
                                                <span class="text-sm tabular-nums text-white/50">{{ $order->placed_at?->setTimezone($tz)->format('H:i') }}</span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3">{{ $order->customer?->firstName() ?? '—' }}</td>
                                        <td class="max-w-[12rem] truncate px-4 py-3 text-white/75">
                                            <bdi>{{ $order->vehicle['label'] ?? '—' }}</bdi>
                                            @if ($order->lane)<span class="block text-sm text-white/45">{{ $order->lane->name }}</span>@endif
                                        </td>
                                        <td class="px-4 py-3 text-end tabular-nums" dir="ltr">{{ Money::format($order->total_minor, $currency) }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="block">{{ $order->payment_method->getLabel() }}</span>
                                            <span @class(['text-white/45', 'text-status-ready' => $order->payment_status === PaymentStatus::Paid, 'text-status-delayed' => $order->payment_status === PaymentStatus::Failed])>{{ $order->staffPaymentLabel() }}</span>
                                        </td>
                                        <td class="px-4 py-3"><span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusTone[$order->status->value] }}">{{ $order->status->getLabel() }}</span></td>
                                        <td class="px-4 py-3 tabular-nums" dir="ltr" x-data="{ at: {{ ($order->received_at ?? $order->placed_at)?->getTimestampMs() ?? 0 }} }" x-text="Math.max(0, Math.floor(($store.clock.now - at) / 60000)) + ' ' + @js(__('ops.min'))"></td>
                                        <td class="px-4 py-3 text-sm">
                                            @if ($order->arrived_at)
                                                <span class="font-semibold text-sage-100">{{ __('ops.badges.arrived') }}</span>
                                            @elseif ($order->last_distance_m !== null && $order->last_location_at?->gt(now()->subMinutes(3)))
                                                <span class="text-ice-100">{{ __('ops.board.distance', ['km' => number_format($order->last_distance_m / 1000, 1)]) }}</span>
                                            @elseif ($order->location_denied_at)
                                                <span class="text-white/45">{{ __('ops.board.location_off') }}</span>
                                            @elseif ($order->ready_estimate_at && $order->status->isActive())
                                                <span class="tabular-nums text-white/70">{{ __('ops.board.ready_at', ['time' => $order->ready_estimate_at->setTimezone($tz)->format('H:i')]) }}</span>
                                            @else
                                                <span class="text-white/40">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            @if ($next && ($order->status === OrderStatus::Ready ? $can['pickup'] : $can['operate']))
                                                <button type="button" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled" class="min-h-12 rounded-xl bg-white px-4 text-sm font-semibold text-ink-950 transition active:scale-95 disabled:opacity-50">{{ $next }}</button>
                                            @elseif ($order->status === OrderStatus::Ready && $can['collect'] && $order->payment_status !== PaymentStatus::Paid)
                                                <button type="button" wire:click="select({{ $order->id }})" class="min-h-12 rounded-xl bg-brass-300 px-4 text-sm font-semibold text-ink-950">{{ __('ops.actions.collect') }}</button>
                                            @else
                                                <button type="button" wire:click="select({{ $order->id }})" class="min-h-12 rounded-xl bg-white/8 px-4 text-sm font-semibold">{{ __('ops.actions.details') }}</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endunless

        {{-- Done table --}}
        <section class="px-4 py-6 sm:px-6" aria-labelledby="done-title">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="done-title" class="me-2 text-xl font-semibold">{{ $filter === 'cancelled' ? __('ops.board.cancelled_title') : __('ops.board.done_title') }}</h2>
                @foreach (\App\Livewire\Ops\LiveBoard::RANGES as $key)
                    @continue($key === 'day')
                    <button type="button" wire:click="$set('range', '{{ $key }}')" @class(['min-h-11 rounded-full px-4 text-sm font-medium ring-1', 'bg-white text-ink-950 ring-white' => $range === $key, 'bg-white/5 text-white/70 ring-white/10' => $range !== $key])>{{ __('ops.board.range.'.$key) }}</button>
                @endforeach
                <label class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/5 px-4 text-sm text-white/70 ring-1 ring-white/10">
                    <span>{{ __('ops.board.range.day') }}</span>
                    <input type="date" wire:model.live="day" x-on:change="$wire.set('range', 'day')" max="{{ now($tz)->format('Y-m-d') }}" dir="ltr" class="border-0 bg-transparent p-0 text-sm text-white focus:ring-0 [color-scheme:dark]">
                </label>
                <span class="ms-auto text-sm text-white/55">{{ __('ops.board.done_total') }} <span class="font-semibold tabular-nums text-white" dir="ltr">{{ Money::format($doneTotal, $currency) }}</span></span>
            </div>

            @if ($done->isEmpty())
                <p class="mt-4 rounded-3xl border border-dashed border-white/10 px-6 py-8 text-center text-white/40">{{ __('ops.board.done_empty') }}</p>
            @else
                <div class="relative mt-4 overflow-x-auto rounded-3xl ring-1 ring-white/8">
                    <table class="w-full min-w-[40rem] text-base">
                        <tbody class="divide-y divide-white/6">
                            @foreach ($done as $order)
                                <tr wire:key="done-{{ $order->id }}" class="hover:bg-white/[0.03]">
                                    <td class="px-4 py-3"><button type="button" wire:click="select({{ $order->id }})" class="font-semibold tabular-nums">{{ $order->displayNumber() }}</button></td>
                                    <td class="px-4 py-3 text-white/70">{{ $order->customer?->firstName() ?? '—' }}</td>
                                    <td class="max-w-[14rem] truncate px-4 py-3 text-white/60"><bdi>{{ $order->vehicle['label'] ?? '—' }}</bdi></td>
                                    <td class="px-4 py-3 tabular-nums text-white/60">{{ ($order->picked_up_at ?? $order->cancelled_at)?->setTimezone($tz)->format($range === 'today' || $range === 'yesterday' ? 'H:i' : 'd/m H:i') }}</td>
                                    <td class="px-4 py-3 text-sm text-white/55">@if ($order->cancel_reason){{ $order->cancel_reason }}@elseif ($order->received_at && $order->ready_at){{ __('ops.board.took', ['minutes' => (int) $order->received_at->diffInMinutes($order->ready_at)]) }}@endif</td>
                                    <td class="px-4 py-3 text-end tabular-nums" dir="ltr">{{ Money::format($order->total_minor, $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($doneMore)
                    <button type="button" wire:click="showMore" class="mt-3 min-h-12 w-full rounded-2xl bg-white/5 text-base font-medium text-white/70 ring-1 ring-white/8">{{ __('ops.board.show_more') }}</button>
                @endif
            @endif
        </section>
    </x-ops.shell>

    {{-- Order panel --}}
    @if ($selectedOrder)
        @include('livewire.ops.partials.order-panel', ['order' => $selectedOrder])
    @endif

    @include('ops.partials.reason-sheet')
</div>
