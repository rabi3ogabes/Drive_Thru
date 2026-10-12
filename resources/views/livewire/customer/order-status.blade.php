@php
    use App\Domain\Ordering\Enums\OrderStatus as Status;
    use App\Support\Money;
    $status = $order->status;
    $step = $status->step();
    $reached = $order->history->mapWithKeys(fn ($h) => [$h->to->value => $h->created_at->setTimezone($tz)]);
    $currency = $order->currency;
    $ready = $order->ready_estimate_at?->setTimezone($tz);
    $card = 'rounded-[1.75rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5';
    $core = 'rounded-[calc(1.75rem-0.375rem)] p-4';
@endphp
<div @if ($live && ! $status->isFinal()) data-live-channel="order.{{ $order->short_code }}" data-live-auth="{{ route('realtime.auth') }}" x-on:live-update.debounce.100ms="$wire.$refresh()" wire:poll.30s.visible @elseif (! $status->isFinal()) wire:poll.5s.visible @endif
     x-data="{
        readyOpen: false,
        notify: 'Notification' in window && Notification.permission === 'granted',
        key: 'dtp.ready.{{ $order->id }}',
        init() {
            let seen = false;
            try { seen = localStorage.getItem(this.key) === '1' } catch (e) {}
            if (@js($owner && $status === Status::Ready) && ! seen) this.readyOpen = true;
        },
        changed(detail) {
            this.chime();
            navigator.vibrate?.(detail.status === 'ready' ? [200, 100, 200, 100, 300] : [120]);
            if (detail.status !== 'ready' || ! @js($owner)) return;
            this.readyOpen = true;
            if (document.hidden && this.notify) {
                try { new Notification(@js(__('guest.order.ready_title')), { body: @js(__('guest.order.ready_body', ['number' => $order->displayNumber()])), tag: this.key }) } catch (e) {}
            }
        },
        dismiss() { this.readyOpen = false; try { localStorage.setItem(this.key, '1') } catch (e) {} },
        async askNotify() {
            if (! ('Notification' in window)) return;
            // Web push reaches the guest even with this page closed; plain notifications are the fallback.
            try {
                if (window.dtpPush && (await window.dtpPush.enable({ restaurant: @js($restaurant->slug) })) === 'on') {
                    this.notify = true;
                    return;
                }
            } catch (e) {}
            this.notify = (await Notification.requestPermission()) === 'granted';
        },
        chime() {
            try {
                const audio = this.audio ??= new (window.AudioContext || window.webkitAudioContext)();
                audio.resume?.();
                [659.25, 830.61, 987.77, 1318.51].forEach((frequency, i) => {
                    const at = audio.currentTime + i * 0.13, osc = audio.createOscillator(), gain = audio.createGain();
                    osc.type = 'sine'; osc.frequency.value = frequency;
                    gain.gain.setValueAtTime(0.0001, at);
                    gain.gain.exponentialRampToValueAtTime(0.25, at + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, at + 1);
                    osc.connect(gain).connect(audio.destination);
                    osc.start(at); osc.stop(at + 1.1);
                });
            } catch (e) {}
        },
     }"
     x-on:order-status.window="changed($event.detail)">
    @if ($live && ! $status->isFinal())
        @vite('resources/js/live.js')
    @endif
    <x-customer.shell :restaurant="$restaurant" :customer="$customer" active="orders" :menu-url="route('public.branch', [$restaurant, $order->branch->code])">
        {{-- Just placed: a short, calm celebration --}}
        @if ($celebrate)
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 2200)" x-show="show" x-transition:leave="transition duration-500" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 grid place-items-center bg-ivory-50/95 backdrop-blur" aria-hidden="true">
                <div class="text-center">
                    <svg class="mx-auto size-24" viewBox="0 0 96 96" fill="none">
                        <circle cx="48" cy="48" r="44" stroke="var(--brand)" stroke-width="4" stroke-linecap="round" pathLength="1" stroke-dasharray="1" stroke-dashoffset="1" style="animation: dt-draw .7s cubic-bezier(.32,.72,0,1) forwards"/>
                        <path d="m30 49 12 12 24-26" stroke="var(--brand)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" pathLength="1" stroke-dasharray="1" stroke-dashoffset="1" style="animation: dt-draw .45s .55s cubic-bezier(.32,.72,0,1) forwards"/>
                    </svg>
                    <p class="display mt-5 text-2xl text-ink-950">{{ __('guest.order.placed') }}</p>
                </div>
                <style>@keyframes dt-draw { to { stroke-dashoffset: 0 } } @media (prefers-reduced-motion: reduce) { [style*=dt-draw] { animation: none !important; stroke-dashoffset: 0 !important } }</style>
            </div>
        @endif

        {{-- Ready: full-screen, hard to miss, easy to dismiss --}}
        @if ($owner && $status === Status::Ready)
            <div x-show="readyOpen" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-[var(--brand)] px-6 text-center text-white" role="alertdialog" aria-modal="true" aria-labelledby="ready-title">
                <div class="max-w-sm">
                    <div class="mx-auto grid size-28 place-items-center rounded-full bg-white/15 ring-8 ring-white/10 motion-safe:animate-pulse">
                        <svg class="size-14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h2 id="ready-title" class="display mt-8 text-4xl">{{ __('guest.order.ready_title') }}</h2>
                    <p class="mt-3 text-lg text-white/85">{{ __('guest.order.ready_body', ['number' => $order->displayNumber()]) }}</p>
                    <p class="mt-6 text-sm uppercase tracking-[0.16em] text-white/70 rtl:tracking-normal">{{ __('guest.order.pickup_code') }}</p>
                    <p class="mt-1 font-mono text-5xl font-semibold tracking-[0.2em]" dir="ltr">{{ $order->pickup_code }}</p>
                    <div class="mt-10 grid gap-3">
                        <button type="button" x-on:click="dismiss()" class="min-h-14 rounded-full bg-white text-lg font-semibold text-ink-950">{{ __('guest.order.ready_ok') }}</button>
                        <button type="button" x-on:click="chime(); navigator.vibrate?.([200, 100, 200])" class="min-h-12 rounded-full text-base font-medium text-white/85">{{ __('guest.order.replay') }}</button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Status hero --}}
        <section class="pt-4">
            <p class="text-sm font-medium text-ink-500">{{ __('guest.order.number', ['number' => $order->displayNumber()]) }} · {{ $order->branch->name }}</p>
            <h1 class="display mt-1 text-[2rem] leading-tight text-ink-950" aria-live="polite">
                @if ($waiting)
                    {{ __('guest.order.waiting_for_you') }}
                @else
                    {{ $status->getLabel() }}
                @endif
            </h1>
            @if ($status->isActive() && ! $waiting && $ready)
                <p class="mt-1 text-ink-600">
                    @if ($status === Status::Ready)
                        {{ __('guest.order.ready_now') }}
                    @else
                        {{ __('guest.order.ready_around', ['time' => $ready->format('H:i')]) }}
                    @endif
                </p>
            @elseif ($order->scheduled_for && ! $status->isFinal())
                <p class="mt-1 text-ink-600">{{ __('guest.order.scheduled_for', ['time' => $order->scheduled_for->setTimezone($tz)->translatedFormat('D j M, H:i')]) }}</p>
            @elseif ($status === Status::Scheduled && $order->prepare_at)
                <p class="mt-1 text-ink-600">{{ __('guest.order.starts_at', ['time' => $order->prepare_at->setTimezone($tz)->format('H:i'), 'ready' => $ready?->format('H:i')]) }}</p>
            @endif
        </section>

        {{-- Payment needed --}}
        @if ($owner && $status === Status::PendingPayment)
            <section class="mt-5 {{ $card }}">
                <div class="{{ $core }} bg-gradient-to-br from-brass-50 to-white">
                    <p class="font-semibold text-ink-950">{{ $paymentFailed ? __('guest.order.payment_failed') : __('guest.order.payment_pending') }}</p>
                    <p class="mt-0.5 text-sm text-ink-600">{{ __('guest.order.payment_hint') }}</p>
                    <div class="mt-3 grid gap-2">
                        @foreach ($onlineMethods as $method)
                            <button type="button" wire:click="pay('{{ $method->value }}')" wire:loading.attr="disabled"
                                    @class(['flex items-center justify-between rounded-full px-5 py-3 font-semibold transition active:scale-[0.98] disabled:opacity-60',
                                            'bg-[var(--brand)] text-white' => $method === $order->payment_method,
                                            'bg-white text-ink-900 ring-1 ring-ink-950/10' => $method !== $order->payment_method])>
                                <span class="flex items-center gap-2"><x-filament::icon :icon="$method->getIcon()" class="size-5" /> {{ __('guest.order.pay_with', ['method' => $method->getLabel()]) }}</span>
                                <span class="tabular-nums" dir="ltr">{{ Money::format($order->total_minor, $currency) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- I'm on the way --}}
        @if ($owner && $waiting && $status !== Status::PendingPayment)
            <section class="mt-5">
                <button type="button" wire:click="onTheWay" wire:loading.attr="disabled"
                        class="flex w-full items-center justify-between gap-3 rounded-full bg-[var(--brand)] py-2 pe-2 ps-6 text-white shadow-lg transition active:scale-[0.985]">
                    <span class="font-semibold">{{ __('guest.order.on_the_way') }}</span>
                    <span class="grid size-11 place-items-center rounded-full bg-white/15" aria-hidden="true">
                        <svg class="flip-rtl size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 13h2l2-5h10l2 5h2M5 13v4h2m10 0h2v-4M7 17a2 2 0 1 0 4 0 2 2 0 0 0-4 0Zm6 0a2 2 0 1 0 4 0" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </button>
                <p class="mt-2 text-center text-sm text-ink-500">{{ __('guest.order.on_the_way_hint', ['minutes' => $order->prep_minutes]) }}</p>
            </section>
        @endif

        {{-- At the branch / on the way: arrival, live location, ready notification --}}
        @if ($owner && $status->isOpen() && $status !== Status::PendingPayment)
            <section class="mt-5 {{ $card }}" aria-labelledby="arrive-title">
                <div class="{{ $core }} space-y-4">
                    @if ($order->arrived_at)
                        <p id="arrive-title" class="flex items-center gap-2 font-semibold text-ink-950">
                            <span class="grid size-8 place-items-center rounded-full bg-sage-100 text-teal-900"><svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            {{ $order->lane ? __('guest.order.here_at', ['lane' => $order->lane->name]) : __('guest.order.here') }}
                        </p>
                        @if ($lanes->count() > 1)
                            <div class="flex flex-wrap gap-2">
                                @foreach ($lanes as $lane)
                                    <button type="button" wire:click="imHere({{ $lane->id }})" @class(['min-h-11 rounded-full px-4 text-sm font-medium ring-1', 'bg-[var(--brand)] text-white ring-[var(--brand)]' => $order->lane_id === $lane->id, 'bg-white text-ink-700 ring-ink-950/10' => $order->lane_id !== $lane->id])>{{ $lane->name }}</button>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div>
                            <h2 id="arrive-title" class="font-semibold text-ink-950">{{ __('guest.order.arrive_title') }}</h2>
                            <p class="mt-0.5 text-sm text-ink-500">{{ __('guest.order.arrive_hint') }}</p>
                        </div>
                        @if ($lanes->count() > 1)
                            <div class="flex flex-wrap gap-2">
                                @foreach ($lanes as $lane)
                                    <button type="button" wire:click="imHere({{ $lane->id }})" class="min-h-12 rounded-full bg-white px-4 text-sm font-semibold text-ink-800 ring-1 ring-ink-950/10 transition active:scale-95">{{ __('guest.order.here_in', ['lane' => $lane->name]) }}</button>
                                @endforeach
                            </div>
                        @endif
                        <button type="button" wire:click="imHere" wire:loading.attr="disabled" class="flex min-h-14 w-full items-center justify-center rounded-full bg-ink-950 font-semibold text-white transition active:scale-[0.98]">{{ __('guest.order.im_here') }}</button>
                    @endif

                    @if ($tracking)
                        <div class="rounded-2xl bg-ivory-50 p-3 ring-1 ring-ink-950/5"
                             x-data="{
                                on: false, distance: null, eta: null, watch: null, last: 0,
                                every: {{ max(5, $order->branch->location_ping_seconds) * 1000 }},
                                key: 'dtp.loc.{{ $order->id }}',
                                init() { try { if (sessionStorage.getItem(this.key) === 'on') this.start() } catch (e) {} },
                                start() {
                                    if (! navigator.geolocation) return;
                                    this.on = true;
                                    try { sessionStorage.setItem(this.key, 'on') } catch (e) {}
                                    this.watch = navigator.geolocation.watchPosition(
                                        (p) => this.send(p),
                                        (e) => { if (e.code === 1) { this.stop(); $wire.locationDenied() } },
                                        { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 },
                                    );
                                },
                                async send(p) {
                                    if (Date.now() - this.last < this.every) return;
                                    this.last = Date.now();
                                    const r = await $wire.ping(p.coords.latitude, p.coords.longitude);
                                    this.distance = r?.distance_m ?? null;
                                    this.eta = r?.eta_minutes ?? null;
                                    if (r?.arrived) this.stop();
                                },
                                stop() {
                                    if (this.watch !== null) navigator.geolocation.clearWatch(this.watch);
                                    this.watch = null;
                                    this.on = false;
                                    try { sessionStorage.removeItem(this.key) } catch (e) {}
                                },
                                destroy() { if (this.watch !== null) navigator.geolocation.clearWatch(this.watch) },
                                get away() { return this.distance === null ? '' : (this.distance >= 1000 ? (this.distance / 1000).toFixed(1) + ' ' + @js(__('guest.home.km')) : this.distance + ' ' + @js(__('guest.order.metres'))) },
                             }">
                            <div x-show="! on" class="flex items-center gap-3">
                                <svg class="size-5 shrink-0 text-ink-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                                <p class="flex-1 text-sm text-ink-600">{{ __('guest.order.share_hint') }}</p>
                                <button type="button" x-on:click="start()" class="min-h-11 shrink-0 rounded-full bg-white px-4 text-sm font-semibold text-ink-900 ring-1 ring-ink-950/10">{{ __('guest.order.share_location') }}</button>
                            </div>
                            <div x-show="on" x-cloak class="flex items-center gap-3">
                                <span class="relative flex size-3 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-status-ready opacity-50"></span><span class="relative inline-flex size-3 rounded-full bg-status-ready"></span></span>
                                <p class="flex-1 text-sm text-ink-700">
                                    <span x-show="distance === null">{{ __('guest.order.locating') }}</span>
                                    <span x-show="distance !== null" x-text="@js(__('guest.order.distance', ['away' => '__A__', 'minutes' => '__M__'])).replace('__A__', away).replace('__M__', eta ?? '—')"></span>
                                </p>
                                <button type="button" x-on:click="stop()" class="min-h-11 shrink-0 rounded-full px-3 text-sm font-medium text-ink-500">{{ __('guest.order.stop_sharing') }}</button>
                            </div>
                        </div>
                    @endif

                    @if ($status !== Status::Ready)
                        <button type="button" x-show="'Notification' in window && ! notify" x-cloak x-on:click="askNotify()" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-full text-sm font-medium text-[var(--brand)]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('guest.order.notify_me') }}
                        </button>
                    @endif
                </div>
            </section>
        @endif

        {{-- Timeline --}}
        @if ($status === Status::Cancelled)
            <p class="mt-5 rounded-2xl bg-white px-4 py-3 text-ink-700 ring-1 ring-ink-950/5">{{ __('guest.order.cancelled_note') }}</p>
        @elseif ($step !== null)
            <ol class="mt-6 grid grid-cols-4 gap-1.5" aria-label="{{ __('guest.order.progress') }}">
                @foreach ($steps as $i => $s)
                    <li class="min-w-0">
                        <div class="h-1.5 overflow-hidden rounded-full bg-ink-950/8">
                            <div @class(['h-full rounded-full bg-[var(--brand)] transition-all duration-700',
                                         'w-full' => $i < $step || ($i === $step && $s === Status::PickedUp) || ($i === $step && $s === Status::Ready),
                                         'w-1/2 animate-pulse' => $i === $step && ! in_array($s, [Status::Ready, Status::PickedUp], true),
                                         'w-0' => $i > $step])></div>
                        </div>
                        <p @class(['mt-2 truncate text-xs font-medium', 'text-ink-950' => $i <= $step, 'text-ink-400' => $i > $step])>{{ $s->getLabel() }}</p>
                        <p class="text-[0.6875rem] tabular-nums text-ink-400">{{ isset($reached[$s->value]) ? $reached[$s->value]->format('H:i') : ' ' }}</p>
                    </li>
                @endforeach
            </ol>
        @endif

        {{-- Pickup pass --}}
        @if ($owner && $qr)
            <section class="mt-6 {{ $card }}" aria-labelledby="pass-title">
                <div class="{{ $core }} flex items-center gap-4">
                    <div class="size-28 shrink-0 rounded-2xl bg-white p-1.5 ring-1 ring-ink-950/8 [&>svg]:size-full" role="img" aria-label="{{ __('guest.order.qr') }}">{!! $qr !!}</div>
                    <div class="min-w-0">
                        <h2 id="pass-title" class="text-xs font-semibold uppercase tracking-[0.16em] text-ink-500 rtl:tracking-normal">{{ __('guest.order.pickup_code') }}</h2>
                        <p class="mt-1 font-mono text-3xl font-semibold tracking-[0.2em] text-ink-950" dir="ltr">{{ $order->pickup_code }}</p>
                        @if ($order->vehicle)
                            <p class="mt-1 truncate text-sm text-ink-500"><bdi>{{ $order->vehicle['label'] ?? $order->vehicle['plate'] ?? '' }}</bdi></p>
                        @endif
                        <p class="mt-1 text-xs text-ink-400">{{ __('guest.order.show_at_window') }}</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- Polite install prompt: only after a real order, only when the browser offers it, never again once dismissed --}}
        @if ($owner)
            <div x-data="{
                    prompt: null,
                    show: false,
                    init() {
                        let seen = false;
                        try { seen = localStorage.getItem('dtp.install') === 'done' } catch (e) {}
                        if (seen || matchMedia('(display-mode: standalone)').matches) return;
                        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.prompt = e; this.show = true });
                    },
                    done() { this.show = false; try { localStorage.setItem('dtp.install', 'done') } catch (e) {} },
                    async install() { if (! this.prompt) return; this.prompt.prompt(); await this.prompt.userChoice; this.done() },
                 }" x-show="show" x-cloak x-transition.opacity
                 class="mt-4 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 ring-1 ring-ink-950/5">
                <x-public.restaurant-logo :restaurant="$restaurant" class="size-9 !ring-0" />
                <p class="min-w-0 flex-1 text-sm text-ink-700">{{ __('guest.install.body', ['restaurant' => $restaurant->name]) }}</p>
                <button type="button" x-on:click="done()" class="text-sm font-medium text-ink-400">{{ __('guest.install.later') }}</button>
                <button type="button" x-on:click="install()" class="rounded-full bg-ink-950 px-3.5 py-2 text-sm font-semibold text-white">{{ __('guest.install.add') }}</button>
            </div>
        @endif

        {{-- Share --}}
        @if (! $status->isFinal())
            <div class="mt-4 flex items-center justify-between gap-3 rounded-2xl bg-white/70 px-4 py-2.5 ring-1 ring-ink-950/5"
                 x-data="{ copied: false, async share() { const url = @js($shareUrl); if (navigator.share) { try { await navigator.share({ url }) } catch (e) {} return } await navigator.clipboard?.writeText(url); this.copied = true; setTimeout(() => this.copied = false, 1800) } }">
                <span class="text-sm text-ink-600">{{ __('guest.order.code') }} <span class="font-mono font-semibold tracking-wider text-ink-950" dir="ltr">{{ $order->short_code }}</span></span>
                <button type="button" x-on:click="share()" class="text-sm font-semibold text-[var(--brand)]">
                    <span x-show="! copied">{{ __('guest.order.share') }}</span>
                    <span x-show="copied" x-cloak>{{ __('guest.order.copied') }}</span>
                </button>
            </div>
        @endif

        {{-- What's in it --}}
        @if ($owner)
            <section class="mt-6 {{ $card }}" aria-labelledby="items-title">
                <div class="{{ $core }}">
                    <div class="flex items-baseline justify-between">
                        <h2 id="items-title" class="font-semibold text-ink-950">{{ __('guest.order.items') }}</h2>
                        <a href="{{ route('customer.receipt', [$restaurant, $order]) }}" target="_blank" class="text-sm font-medium text-[var(--brand)]">{{ __('guest.order.receipt') }}</a>
                    </div>
                    <ul class="mt-2 divide-y divide-ink-950/5">
                        @foreach ($order->items as $item)
                            <li class="flex justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="font-medium text-ink-900"><span class="tabular-nums text-ink-500">{{ $item->quantity }}×</span> {{ $item->localName() }}</p>
                                    @if ($labels = $item->optionLabels())<p class="text-[0.8125rem] text-ink-500">{{ implode('، ', $labels) }}</p>@endif
                                </div>
                                <p class="shrink-0 text-sm tabular-nums text-ink-700" dir="ltr">{{ Money::format($item->line_total_minor, $currency) }}</p>
                            </li>
                        @endforeach
                    </ul>
                    @foreach ($order->adjustments as $adjustment)
                        @continue($adjustment->amount_minor === 0)
                        <div class="flex justify-between gap-3 py-1 text-sm text-teal-800"><span class="min-w-0 truncate">{{ $adjustment->localLabel() }}</span><span class="tabular-nums" dir="ltr">−{{ Money::format($adjustment->amount_minor, $currency) }}</span></div>
                    @endforeach
                    <div class="mt-2 flex justify-between border-t border-ink-950/8 pt-3 font-semibold text-ink-950">
                        <span>{{ __('guest.checkout.total') }}</span>
                        <span class="tabular-nums" dir="ltr">{{ Money::format($order->total_minor, $currency) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-ink-500">{{ $order->payment_method->getLabel() }}@if ($order->payment_method->isOnline()) · {{ $order->payment_status->getLabel() }}@endif</p>
                    @if ($order->points_earned > 0)
                        <p class="mt-2 text-sm font-medium text-brass-800">{{ __('guest.order.points_earned', ['points' => number_format($order->points_earned)]) }}</p>
                    @endif
                </div>
            </section>

            <div class="mt-6 grid gap-2">
                @if ($status->isFinal())
                    <button type="button" wire:click="orderAgain" class="rounded-full bg-[var(--brand)] py-3.5 font-semibold text-white transition active:scale-[0.98]">{{ __('guest.order.again') }}</button>
                @endif
                @if ($canCancel)
                    <button type="button" wire:click="cancel" wire:confirm="{{ __('guest.order.cancel_confirm') }}" class="rounded-full py-3 text-sm font-semibold text-ink-500 transition hover:text-ink-900">{{ __('guest.order.cancel') }}</button>
                @endif
                @if ($phone = collect($order->branch->phones ?? [])->first())
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="rounded-full py-3 text-center text-sm font-semibold text-ink-500">{{ __('guest.order.call_branch') }}</a>
                @endif
            </div>
        @endif
    </x-customer.shell>
</div>
