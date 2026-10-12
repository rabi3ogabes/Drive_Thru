@php
    use App\Domain\Ordering\Enums\OrderStatus;
    use App\Domain\Ordering\Enums\PaymentMethod;
    use App\Domain\Ordering\Enums\PaymentStatus;
    use App\Support\Money;
    $currency = $order->currency;
    $since = $order->received_at ?? $order->placed_at;
    $unpaidCounter = $order->payment_method === PaymentMethod::PayAtPickup && $order->payment_status !== PaymentStatus::Paid && $order->status !== OrderStatus::Cancelled;
    $btn = 'flex min-h-14 items-center justify-center rounded-2xl px-4 text-base font-semibold transition active:scale-[0.98] disabled:opacity-50';
@endphp
<div class="fixed inset-0 z-40 flex justify-end bg-ink-950/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="panel-title"
     wire:key="panel-{{ $order->id }}" x-data x-on:keydown.escape.window="$wire.select(null)">
    <button type="button" class="absolute inset-0 cursor-default" wire:click="select(null)" aria-label="{{ __('ops.actions.close') }}"></button>

    <aside class="relative flex h-full w-full max-w-xl flex-col bg-ink-900 shadow-2xl ring-1 ring-white/10"
           x-init="matchMedia('(prefers-reduced-motion: reduce)').matches || $el.animate([{ transform: 'translateX(' + (document.dir === 'rtl' ? '-' : '') + '100%)' }, { transform: 'translateX(0)' }], { duration: 320, easing: 'cubic-bezier(.16,1,.3,1)' })">
        <header class="flex items-start justify-between gap-4 border-b border-white/8 p-5">
            <div>
                <h2 id="panel-title" class="text-3xl font-semibold tabular-nums">{{ $order->displayNumber() }}</h2>
                <p class="mt-1 text-white/60">{{ $order->status->getLabel() }} · <span class="tabular-nums">{{ $order->placed_at?->setTimezone($tz)->format('H:i') }}</span> · <span class="font-mono tracking-wider">{{ $order->short_code }}</span></p>
            </div>
            <button type="button" wire:click="select(null)" class="grid size-14 place-items-center rounded-2xl bg-white/6 ring-1 ring-white/10" aria-label="{{ __('ops.actions.close') }}">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
            </button>
        </header>

        <div class="flex-1 space-y-5 overflow-y-auto p-5">
            {{-- Guest and car --}}
            <dl class="grid grid-cols-2 gap-3 text-base">
                <div class="rounded-2xl bg-white/[0.04] p-4">
                    <dt class="text-sm text-white/50">{{ __('ops.board.col.customer') }}</dt>
                    <dd class="mt-1 font-semibold">{{ $order->customer?->firstName() ?? '—' }}</dd>
                    @if ($can['contacts'] && $order->customer)<dd class="text-sm tabular-nums text-white/60" dir="ltr">{{ $order->customer->phone }}</dd>@endif
                </div>
                <div class="rounded-2xl bg-white/[0.04] p-4">
                    <dt class="text-sm text-white/50">{{ __('ops.board.col.vehicle') }}</dt>
                    <dd class="mt-1 font-semibold"><bdi>{{ $order->vehicle['label'] ?? '—' }}</bdi></dd>
                    @if ($order->lane)<dd class="text-sm text-white/60">{{ $order->lane->name }}</dd>@endif
                </div>
                <div class="rounded-2xl bg-white/[0.04] p-4">
                    <dt class="text-sm text-white/50">{{ __('ops.panel.since_placed') }}</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ __('ops.minutes_short', ['count' => (int) $since?->diffInMinutes(now())]) }}</dd>
                    <dd class="text-sm text-white/60">{{ __('ops.panel.target', ['minutes' => $order->prep_minutes]) }}</dd>
                </div>
                <div class="rounded-2xl bg-white/[0.04] p-4">
                    <dt class="text-sm text-white/50">{{ __('ops.panel.guest') }}</dt>
                    <dd class="mt-1 font-semibold">
                        @if ($order->arrived_at)
                            {{ __('ops.panel.arrived_ago', ['minutes' => (int) $order->arrived_at->diffInMinutes(now())]) }}
                        @elseif ($order->last_distance_m !== null && $order->last_location_at?->gt(now()->subMinutes(3)))
                            {{ __('ops.board.distance', ['km' => number_format($order->last_distance_m / 1000, 1)]) }}
                        @elseif ($order->location_denied_at)
                            {{ __('ops.board.location_off') }}
                        @elseif ($order->on_the_way_at)
                            {{ __('ops.badges.on_the_way') }}
                        @else
                            {{ __('ops.panel.away') }}
                        @endif
                    </dd>
                    @if ($order->ready_estimate_at && $order->status->isActive())<dd class="text-sm text-white/60">{{ __('ops.board.ready_at', ['time' => $order->ready_estimate_at->setTimezone($tz)->format('H:i')]) }}</dd>@endif
                </div>
            </dl>

            {{-- Items --}}
            <section aria-label="{{ __('ops.panel.items') }}">
                <ul class="space-y-2.5">
                    @foreach ($order->items as $item)
                        <li class="flex gap-3">
                            <span class="grid h-8 min-w-8 place-items-center rounded-lg bg-white/10 px-1.5 font-semibold tabular-nums">{{ $item->quantity }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $item->localName() }}</p>
                                @if ($labels = $item->optionLabels())<p class="text-sm text-white/60">{{ implode(' · ', $labels) }}</p>@endif
                                @if ($item->notes)<p class="text-sm text-brass-100">“{{ $item->notes }}”</p>@endif
                            </div>
                            <span class="tabular-nums text-white/70" dir="ltr">{{ Money::format($item->line_total_minor, $currency) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($order->notes)<p class="mt-3 rounded-xl bg-brass-400/12 px-3 py-2 text-brass-100">{{ $order->notes }}</p>@endif
                <div class="mt-4 flex items-center justify-between border-t border-white/8 pt-3 text-lg font-semibold">
                    <span>{{ __('ops.panel.total') }}</span>
                    <span class="tabular-nums" dir="ltr">{{ Money::format($order->total_minor, $currency) }}</span>
                </div>
                <p class="text-sm text-white/55">{{ $order->payment_method->getLabel() }} · {{ $order->staffPaymentLabel() }}</p>
                @if ($order->pos_synced_at)
                    <p class="mt-1 text-sm text-status-ready">{{ $order->pos_reference ? __('ops.panel.pos_synced_ref', ['ref' => $order->pos_reference]) : __('ops.panel.pos_synced') }}</p>
                @elseif ($order->pos_error)
                    <p class="mt-1 text-sm text-status-delayed">{{ __('ops.panel.pos_failed', ['reason' => $order->pos_error]) }}</p>
                @endif
            </section>

            @if ($order->cancel_reason)
                <p class="rounded-2xl bg-white/5 px-4 py-3 text-white/70">{{ __('ops.panel.cancelled_because', ['reason' => $order->cancel_reason]) }}</p>
            @endif

            {{-- History --}}
            <details class="rounded-2xl bg-white/[0.03] ring-1 ring-white/8">
                <summary class="flex min-h-12 cursor-pointer list-none items-center px-4 text-white/70 [&::-webkit-details-marker]:hidden">{{ __('ops.panel.history') }}</summary>
                <ol class="space-y-1 px-4 pb-3 text-sm text-white/65">
                    @foreach ($order->history as $change)
                        <li class="flex justify-between gap-3"><span>{{ $change->to->getLabel() }}@if ($change->note && ! str_contains($change->note, '.')) · {{ $change->note }}@endif</span><span class="tabular-nums">{{ $change->created_at?->setTimezone($tz)->format('H:i') }}</span></li>
                    @endforeach
                </ol>
            </details>
        </div>

        {{-- Actions --}}
        <footer class="space-y-2 border-t border-white/8 p-5">
            @if ($unpaidCounter && $can['collect'])
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="collect({{ $order->id }}, 'cash')" wire:loading.attr="disabled" class="{{ $btn }} bg-brass-300 text-ink-950">{{ __('ops.actions.collect_cash') }}</button>
                    <button type="button" wire:click="collect({{ $order->id }}, 'card')" wire:loading.attr="disabled" class="{{ $btn }} bg-brass-300 text-ink-950">{{ __('ops.actions.collect_card') }}</button>
                </div>
            @endif

            @if (in_array($order->status, [OrderStatus::Received, OrderStatus::Preparing], true) && $can['operate'])
                <button type="button" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled" class="{{ $btn }} w-full bg-white text-ink-950">{{ $order->status === OrderStatus::Received ? __('ops.actions.start') : __('ops.actions.ready') }}</button>
            @elseif ($order->status === OrderStatus::Ready && $can['pickup'])
                <button type="button" wire:click="advance({{ $order->id }})" wire:loading.attr="disabled" @disabled($order->payment_status !== PaymentStatus::Paid) class="{{ $btn }} w-full bg-status-ready text-white">{{ __('ops.actions.hand_over') }}</button>
            @endif

            @if (! $order->arrived_at && $order->status->isOpen() && $order->status !== OrderStatus::PendingPayment && $can['pickup'])
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="arrived({{ $order->id }})" class="{{ $btn }} flex-1 bg-white/8">{{ __('ops.actions.mark_arrived') }}</button>
                    @foreach ($lanes as $lane)
                        <button type="button" wire:click="arrived({{ $order->id }}, {{ $lane->id }})" class="{{ $btn }} bg-white/8">{{ $lane->name }}</button>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                @if ($order->status->isOpen() && $can['operate'])
                    <button type="button" class="{{ $btn }} bg-white/6 text-ice-50 ring-1 ring-status-delayed/40"
                            x-on:click="$dispatch('ops-reason', { method: 'cancel', args: [{{ $order->id }}], title: @js(__('ops.reason.cancel_title', ['number' => $order->displayNumber()])), action: @js(__('ops.actions.cancel_order')), tone: 'danger', choices: @js(__('ops.reason.cancel_choices')) })">{{ __('ops.actions.cancel_order') }}</button>
                @endif
                @if ($order->status === OrderStatus::Cancelled && $can['operate'])
                    <button type="button" class="{{ $btn }} bg-white/8"
                            x-on:click="$dispatch('ops-reason', { method: 'reEnable', args: [{{ $order->id }}], title: @js(__('ops.reason.enable_title', ['number' => $order->displayNumber()])), action: @js(__('ops.actions.re_enable')), tone: 'neutral', choices: @js(__('ops.reason.enable_choices')) })">{{ __('ops.actions.re_enable') }}</button>
                @endif
                <div x-data="{ copied: false }" class="contents">
                    <button type="button" class="{{ $btn }} bg-white/6"
                            x-on:click="navigator.clipboard?.writeText(@js($staffLink)); copied = true; setTimeout(() => copied = false, 1600)">
                        <span x-show="! copied">{{ __('ops.actions.copy_link') }}</span><span x-show="copied" x-cloak>{{ __('ops.actions.copied') }}</span>
                    </button>
                </div>
                <a href="{{ $staffLink }}" target="_blank" rel="noopener" class="{{ $btn }} bg-white/6">{{ __('ops.actions.open_guest_page') }}</a>
                <button type="button" wire:click="printTicket({{ $order->id }}, 'kitchen')" class="{{ $btn }} bg-white/6">{{ __('print.print_kitchen') }}</button>
                <button type="button" wire:click="printTicket({{ $order->id }}, 'receipt')" class="{{ $btn }} bg-white/6">{{ __('print.print_receipt') }}</button>
            </div>

            @php($refundable = $order->payment?->isOnline() === true ? $order->payment->refundableMinor() : 0)
            @if ($can['refund'] && $refundable > 0)
                <button type="button" class="{{ $btn }} w-full bg-white/6 text-ice-50 ring-1 ring-status-delayed/40"
                        x-on:click="$dispatch('ops-reason', { method: 'refund', args: [{{ $order->id }}], title: @js(__('ops.reason.refund_title', ['amount' => Money::format($refundable, $currency), 'number' => $order->displayNumber()])), action: @js(__('ops.actions.refund')), tone: 'danger', choices: @js(__('ops.reason.refund_choices')), pin: @js($can['pin']) })">{{ __('ops.actions.refund') }}</button>
            @endif

            @if ($can['override'])
                <details class="rounded-2xl bg-white/[0.03] ring-1 ring-white/8">
                    <summary class="flex min-h-14 cursor-pointer list-none items-center px-4 font-medium text-white/75 [&::-webkit-details-marker]:hidden">{{ __('ops.panel.override') }}</summary>
                    <div class="grid grid-cols-2 gap-2 p-3 pt-0">
                        <button type="button" class="{{ $btn }} bg-white/8"
                                x-on:click="$dispatch('ops-reason', { method: 'resetAsNew', args: [{{ $order->id }}], title: @js(__('ops.reason.reset_title', ['number' => $order->displayNumber()])), action: @js(__('ops.actions.reset')), tone: 'neutral', choices: @js(__('ops.reason.override_choices')), pin: @js($can['pin']) })">{{ __('ops.actions.reset') }}</button>
                        @foreach (OrderStatus::cases() as $to)
                            @continue(! $order->status->canOverrideTo($to))
                            <button type="button" class="{{ $btn }} bg-white/5"
                                    x-on:click="$dispatch('ops-reason', { method: 'setStatus', args: [{{ $order->id }}, @js($to->value)], title: @js(__('ops.reason.status_title', ['status' => $to->getLabel()])), action: @js(__('ops.actions.set_status')), tone: 'neutral', choices: @js(__('ops.reason.override_choices')), pin: @js($can['pin']) })">{{ $to->getLabel() }}</button>
                        @endforeach
                    </div>
                </details>
            @endif
        </footer>
    </aside>
</div>
