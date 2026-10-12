@php
    use App\Domain\Ordering\Enums\PaymentMethod;
    use App\Support\Money;
    $btn = 'flex min-h-16 items-center justify-center gap-2 rounded-2xl px-5 text-lg font-semibold transition active:scale-[0.98] disabled:opacity-40';
@endphp
<div wire:poll.20s>
    <x-ops.shell :branch="$branch" :title="__('ops.screens.pickup')" :device-id="$deviceId" screen="pickup">
        <div class="grid gap-5 p-4 sm:p-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            {{-- Scan / type --}}
            <section class="space-y-4" aria-labelledby="scan-title">
                <form wire:submit="lookup" class="rounded-[2rem] bg-white/[0.045] p-5 ring-1 ring-white/8"
                      x-data="{
                        scanning: false,
                        supported: 'BarcodeDetector' in window && !! navigator.mediaDevices?.getUserMedia,
                        stream: null,
                        async scan() {
                            try {
                                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                                this.scanning = true;
                                await this.$nextTick();
                                this.$refs.video.srcObject = this.stream;
                                await this.$refs.video.play();
                                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                                const look = async () => {
                                    if (! this.scanning) return;
                                    const codes = await detector.detect(this.$refs.video).catch(() => []);
                                    if (codes.length) { this.stop(); $wire.set('code', codes[0].rawValue, false); $wire.lookup(); return }
                                    setTimeout(look, 250);
                                };
                                look();
                            } catch (e) { this.stop(); }
                        },
                        stop() { this.scanning = false; this.stream?.getTracks().forEach((t) => t.stop()); this.stream = null },
                      }"
                      x-on:keydown.escape.window="stop()">
                    <h2 id="scan-title" class="text-xl font-semibold">{{ __('ops.pickup.title') }}</h2>
                    <p class="mt-1 text-white/55">{{ __('ops.pickup.hint') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <label class="min-w-0 flex-1">
                            <span class="sr-only">{{ __('ops.pickup.code') }}</span>
                            <input wire:model="code" type="text" inputmode="text" autocomplete="off" autocapitalize="characters" autofocus dir="ltr" maxlength="20"
                                   placeholder="{{ __('ops.pickup.placeholder') }}"
                                   class="min-h-16 w-full rounded-2xl border-0 bg-white/8 px-5 text-center font-mono text-2xl tracking-[0.2em] text-white ring-1 ring-white/12 placeholder:text-white/30 placeholder:tracking-normal focus:ring-2 focus:ring-brass-300">
                        </label>
                        <button type="submit" class="{{ $btn }} bg-white text-ink-950">{{ __('ops.pickup.find') }}</button>
                        <button type="button" x-show="supported" x-cloak x-on:click="scan()" class="{{ $btn }} bg-white/8 ring-1 ring-white/12">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10" stroke-linecap="round"/></svg>
                            {{ __('ops.pickup.scan') }}
                        </button>
                    </div>
                    @if ($notice)<p class="mt-3 rounded-xl bg-status-delayed/15 px-4 py-3 text-ice-50" role="alert">{{ $notice }}</p>@endif

                    <div x-show="scanning" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-ink-950/90 p-6" role="dialog" aria-modal="true">
                        <div class="w-full max-w-lg text-center">
                            <video x-ref="video" playsinline muted class="aspect-square w-full rounded-[2rem] bg-black object-cover ring-2 ring-brass-300/60"></video>
                            <p class="mt-4 text-lg text-white/70">{{ __('ops.pickup.point_camera') }}</p>
                            <button type="button" x-on:click="stop()" class="mt-4 min-h-14 rounded-2xl bg-white/10 px-8 text-lg font-semibold">{{ __('ops.actions.close') }}</button>
                        </div>
                    </div>
                </form>

                {{-- The order at the window --}}
                @if ($current)
                    <article class="rounded-[2rem] bg-white/[0.06] p-5 ring-1 ring-white/12" wire:key="current-{{ $current->id }}" aria-live="polite">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-4xl font-semibold tabular-nums">{{ $current->displayNumber() }}</p>
                                <p class="mt-1 text-lg text-white/70">{{ $current->customer?->firstName() ?? '—' }} · <bdi>{{ $current->vehicle['label'] ?? '—' }}</bdi></p>
                            </div>
                            <button type="button" wire:click="clear" class="min-h-12 rounded-xl bg-white/8 px-4 font-medium">{{ __('ops.actions.close') }}</button>
                        </div>

                        <ul class="mt-5 grid grid-cols-3 gap-3" aria-label="{{ __('ops.pickup.checks') }}">
                            @foreach ($checks as $key => $ok)
                                <li @class(['flex min-h-20 flex-col items-center justify-center gap-1 rounded-2xl text-center ring-1', 'bg-status-ready/15 text-sage-100 ring-status-ready/35' => $ok, 'bg-white/5 text-white/60 ring-white/10' => ! $ok])>
                                    @if ($ok)
                                        <svg class="size-7" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @else
                                        <svg class="size-7" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8" stroke-dasharray="3 3"/></svg>
                                    @endif
                                    <span class="text-base font-semibold">{{ __('ops.pickup.check.'.$key) }}</span>
                                    <span class="sr-only">{{ $ok ? __('ops.pickup.yes') : __('ops.pickup.no') }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <ul class="mt-5 space-y-1.5 border-t border-white/8 pt-4 text-lg">
                            @foreach ($current->items as $item)
                                <li><span class="font-semibold tabular-nums">{{ $item->quantity }}×</span> {{ $item->localName() }} @if ($labels = $item->optionLabels())<span class="text-base text-white/55">· {{ implode(' · ', $labels) }}</span>@endif</li>
                            @endforeach
                        </ul>

                        <div class="mt-5 space-y-3">
                            @unless ($checks['verified'])
                                <form wire:submit="verify" class="flex gap-3">
                                    <label class="min-w-0 flex-1">
                                        <span class="sr-only">{{ __('ops.pickup.ask_code') }}</span>
                                        <input wire:model="confirmCode" type="text" inputmode="numeric" maxlength="6" dir="ltr" placeholder="{{ __('ops.pickup.ask_code') }}"
                                               class="min-h-16 w-full rounded-2xl border-0 bg-white/8 px-5 text-center font-mono text-2xl tracking-[0.3em] text-white ring-1 ring-white/12 placeholder:text-base placeholder:tracking-normal placeholder:text-white/35 focus:ring-2 focus:ring-brass-300">
                                    </label>
                                    <button type="submit" class="{{ $btn }} bg-white/10 ring-1 ring-white/15">{{ __('ops.pickup.verify') }}</button>
                                </form>
                            @endunless

                            @if (! $checks['paid'] && $current->payment_method === PaymentMethod::PayAtPickup && $canCollect)
                                <p class="text-center text-lg">{{ __('ops.pickup.to_collect') }} <span class="font-semibold tabular-nums" dir="ltr">{{ Money::format($current->total_minor, $current->currency) }}</span></p>
                                <div class="grid grid-cols-2 gap-3">
                                    <button type="button" wire:click="collect('cash')" wire:loading.attr="disabled" class="{{ $btn }} bg-brass-300 text-ink-950">{{ __('ops.actions.collect_cash') }}</button>
                                    <button type="button" wire:click="collect('card')" wire:loading.attr="disabled" class="{{ $btn }} bg-brass-300 text-ink-950">{{ __('ops.actions.collect_card') }}</button>
                                </div>
                            @endif

                            <button type="button" wire:click="complete" wire:loading.attr="disabled" @disabled(in_array(false, $checks, true))
                                    class="{{ $btn }} w-full bg-status-ready text-xl text-white">{{ __('ops.pickup.complete') }}</button>
                        </div>
                    </article>
                @endif
            </section>

            {{-- Arrived queue and ready orders --}}
            <section class="space-y-5">
                <div>
                    <h2 class="mb-3 flex items-center justify-between text-xl font-semibold">{{ __('ops.pickup.arrived_title') }} <span class="rounded-full bg-white/8 px-3 py-1 text-sm tabular-nums">{{ $arrived->count() }}</span></h2>
                    @forelse ($arrived as $order)
                        <button type="button" wire:key="arr-{{ $order->id }}" wire:click="open({{ $order->id }})"
                                @class(['mb-2 flex min-h-20 w-full items-center gap-4 rounded-3xl bg-white/[0.045] px-5 text-start ring-1 ring-white/8 transition hover:bg-white/[0.08]', 'ops-glow-overdue' => $order->arrived_at->addMinutes(\App\Domain\Ordering\BranchPulse::ARRIVED_WAIT_MINUTES)->isPast()])>
                            <span class="text-2xl font-semibold tabular-nums">{{ $order->displayNumber() }}</span>
                            <span class="min-w-0 flex-1">
                                <bdi class="block truncate text-lg">{{ $order->vehicle['label'] ?? '—' }}</bdi>
                                <span class="text-sm text-white/55">{{ $order->lane?->name ?? __('ops.pickup.any_lane') }} · {{ $order->status->getLabel() }}</span>
                            </span>
                            <span class="text-end tabular-nums text-white/70" dir="ltr" x-data="{ at: {{ $order->arrived_at->getTimestampMs() }} }" x-text="Math.max(0, Math.floor(($store.clock.now - at) / 60000)) + ' ' + @js(__('ops.min'))"></span>
                        </button>
                    @empty
                        <p class="rounded-3xl border border-dashed border-white/10 px-6 py-8 text-center text-white/40">{{ __('ops.pickup.no_arrivals') }}</p>
                    @endforelse
                </div>

                <div>
                    <h2 class="mb-3 flex items-center justify-between text-xl font-semibold">{{ __('ops.pickup.ready_title') }} <span class="rounded-full bg-white/8 px-3 py-1 text-sm tabular-nums">{{ $ready->count() }}</span></h2>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @forelse ($ready as $order)
                            <button type="button" wire:key="rdy-{{ $order->id }}" wire:click="open({{ $order->id }})" class="min-h-16 rounded-2xl bg-status-ready/12 text-xl font-semibold tabular-nums text-sage-100 ring-1 ring-status-ready/30">{{ $order->displayNumber() }}</button>
                        @empty
                            <p class="col-span-full rounded-3xl border border-dashed border-white/10 px-6 py-6 text-center text-white/40">{{ __('ops.pickup.none_ready') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </x-ops.shell>
</div>
