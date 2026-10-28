@php
    use App\Support\Bidi;
    $field = 'min-h-16 w-full rounded-2xl border-0 bg-white/6 px-5 text-2xl tracking-wide text-white ring-1 ring-white/10 placeholder:text-white/30 focus:ring-2 focus:ring-brass-300/60';
@endphp
<div wire:poll.10s.visible>
    <x-ops.shell :branch="$branch" :title="__('ops.screens.stamps')" :device-id="$deviceId" screen="stamps" :channel="'branch.'.$branch->id.'.pager'">
        {{-- Guests who scanned the counter QR and asked for their stamp --}}
        <div x-data="stampChime({{ $waiting->count() }})" x-effect="ring({{ $waiting->count() }})" class="mx-auto max-w-6xl px-4 pt-4 sm:px-6 sm:pt-6">
            @if ($waiting->isNotEmpty())
                <section class="rounded-[2rem] bg-brass-300/10 p-1.5 ring-1 ring-brass-300/30" aria-labelledby="waiting-title" aria-live="polite">
                    <div class="rounded-[calc(2rem-0.375rem)] bg-ink-900/70 p-4 sm:p-5">
                        <h2 id="waiting-title" class="flex items-center gap-3 text-lg font-semibold tracking-tight">
                            <span class="relative flex size-3" aria-hidden="true">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-brass-300 opacity-60 motion-reduce:animate-none"></span>
                                <span class="relative inline-flex size-3 rounded-full bg-brass-300"></span>
                            </span>
                            {{ trans_choice('ops.stamps.waiting', $waiting->count(), ['count' => $waiting->count()]) }}
                        </h2>
                        <ul class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($waiting as $ask)
                                @php
                                    $askCards = $waitingCards->get($ask->customer_id, collect())->keyBy('stamp_program_id');
                                @endphp
                                <li wire:key="ask-{{ $ask->id }}" @class(['flex flex-col gap-3 rounded-[1.5rem] bg-white/[0.06] p-4 ring-1 transition', 'ring-brass-300/60' => $requestId === $ask->id, 'ring-white/10' => $requestId !== $ask->id])>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-brass-300 text-lg font-semibold text-ink-950" aria-hidden="true">{{ mb_strtoupper(mb_substr($ask->customer->name ?? '★', 0, 1)) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-lg font-semibold">{{ $ask->customer->name ?? __('ops.stamps.guest') }}</p>
                                            <p class="text-sm text-white/50"><span dir="ltr">{{ Bidi::ltr($ask->customer->maskedPhone()) }}</span> · {{ $ask->created_at?->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                    @if ($programs->isNotEmpty())
                                        <p class="flex flex-wrap gap-1.5">
                                            @foreach ($programs as $program)
                                                <span class="rounded-full bg-white/8 px-2.5 py-1 text-xs text-white/70">{{ $program->name }} · <span class="tabular-nums" dir="ltr">{{ $askCards->get($program->id)?->stamps ?? 0 }}/{{ $program->stamps_needed }}</span></span>
                                            @endforeach
                                        </p>
                                    @endif
                                    <div class="mt-auto flex gap-2">
                                        @if ($programs->count() === 1)
                                            <button type="button" wire:click="approve({{ $ask->id }})" wire:loading.attr="disabled" wire:target="approve({{ $ask->id }})"
                                                    class="min-h-14 flex-1 rounded-full bg-status-ready px-4 text-base font-semibold text-ink-950 transition active:scale-[0.97] disabled:opacity-60">{{ __('ops.stamps.approve_one') }}</button>
                                        @else
                                            <button type="button" wire:click="open({{ $ask->id }})"
                                                    class="min-h-14 flex-1 rounded-full bg-status-ready px-4 text-base font-semibold text-ink-950 transition active:scale-[0.97]">{{ __('ops.stamps.choose') }}</button>
                                        @endif
                                        <button type="button" wire:click="decline({{ $ask->id }})" wire:confirm="{{ __('ops.stamps.decline_confirm') }}"
                                                class="min-h-14 rounded-full px-5 text-base text-white/60 ring-1 ring-white/12 transition hover:text-white active:scale-[0.97]">{{ __('ops.stamps.decline') }}</button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif
        </div>

        <div class="mx-auto grid max-w-6xl gap-5 p-4 sm:p-6 lg:grid-cols-[22rem_minmax(0,1fr)]">

            {{-- Find the guest --}}
            <section class="rounded-[2rem] bg-white/[0.045] p-1.5 ring-1 ring-white/8 lg:self-start">
                <form wire:submit="find" class="space-y-4 rounded-[calc(2rem-0.375rem)] bg-ink-900/60 p-5 ring-1 ring-white/5">
                    <h2 class="text-2xl font-semibold tracking-tight">{{ __('ops.stamps.find') }}</h2>
                    <label class="block">
                        <span class="text-sm text-white/60">{{ __('ops.stamps.phone') }}</span>
                        <input wire:model="phone" type="tel" inputmode="tel" maxlength="20" dir="ltr" autocomplete="off" placeholder="5555 1234" class="mt-1.5 text-start {{ $field }}">
                    </label>
                    @if ($notice)<p class="text-sm text-ice-100" role="alert">{{ $notice }}</p>@endif
                    <button type="submit" wire:loading.attr="disabled" wire:target="find"
                            class="flex min-h-16 w-full items-center justify-center rounded-full bg-brass-300 px-6 text-lg font-semibold text-ink-950 transition active:scale-[0.98] disabled:opacity-60">
                        {{ __('ops.stamps.open') }}
                    </button>
                    @if ($customer)
                        <button type="button" wire:click="clear" class="flex min-h-12 w-full items-center justify-center rounded-full text-base text-white/60 hover:text-white">{{ __('ops.stamps.next_guest') }}</button>
                    @endif
                </form>
            </section>

            {{-- Their cards and free items --}}
            <section class="min-w-0" aria-live="polite">
                @if (! $customer)
                    <div class="grid min-h-64 place-items-center rounded-[2rem] bg-white/[0.03] p-8 text-center ring-1 ring-white/6">
                        <p class="max-w-sm text-lg text-white/50">{{ $programs->isEmpty() ? __('ops.stamps.no_programs') : __('ops.stamps.intro') }}</p>
                    </div>
                @else
                    @if ($request)
                        <p class="mb-4 flex items-center gap-2 rounded-2xl bg-brass-300/10 px-4 py-3 text-base text-brass-100 ring-1 ring-brass-300/25">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('ops.stamps.asked', ['name' => $customer->name ?? __('ops.stamps.guest')]) }}
                        </p>
                    @endif
                    <header class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-1">
                        <h2 class="text-2xl font-semibold tracking-tight">{{ $customer->name ?? __('ops.stamps.guest') }}</h2>
                        <span class="text-base text-white/50" dir="ltr">{{ Bidi::ltr($customer->maskedPhone()) }}</span>
                    </header>

                    @if ($wallet->isNotEmpty())
                        <ul class="mt-4 space-y-2">
                            @foreach ($wallet as $item)
                                <li wire:key="wallet-{{ $item->id }}" class="flex flex-wrap items-center gap-3 rounded-[1.5rem] bg-brass-300/10 p-4 ring-1 ring-brass-300/30">
                                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brass-300 text-ink-950" aria-hidden="true">
                                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12v8H4v-8M2 7h20v5H2zM12 20V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <p class="min-w-0 flex-1 text-lg font-medium">{{ $item->reward->name }}</p>
                                    <button type="button" wire:click="redeem({{ $item->id }})" wire:confirm="{{ __('ops.stamps.redeem_confirm', ['reward' => $item->reward->name]) }}"
                                            class="min-h-14 rounded-full bg-white px-6 text-base font-semibold text-ink-950 transition active:scale-[0.97]">{{ __('ops.stamps.redeem') }}</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <ul class="mt-4 grid gap-4 xl:grid-cols-2">
                        @foreach ($programs as $program)
                            @php
                                $card = $cards->get($program->id);
                                $left = max(0, $program->daily_cap - ($today->get($program->id) ?? 0));
                                $qty = $quantities[$program->id] ?? 0;
                            @endphp
                            <li wire:key="program-{{ $program->id }}" class="rounded-[2rem] bg-white/[0.045] p-1.5 ring-1 ring-white/8">
                                <div class="rounded-[calc(2rem-0.375rem)] bg-ink-900/55 p-4">
                                    <x-stamp-card :name="$program->name" :needed="$program->stamps_needed" :filled="$card?->stamps ?? 0"
                                                  :target="$program->targetName()" :reward="$program->reward->name" :accent="$branch->restaurant->accent_color" />
                                    <div class="mt-4 flex items-center gap-3">
                                        <div class="flex items-center rounded-full bg-white/6 p-1 ring-1 ring-white/10">
                                            <button type="button" wire:click="step({{ $program->id }}, -1)" @disabled($qty <= 0) class="grid size-12 place-items-center rounded-full text-2xl text-white/80 disabled:opacity-30" aria-label="{{ __('ops.stamps.less') }}">−</button>
                                            <span class="w-10 text-center text-2xl font-semibold tabular-nums" dir="ltr">{{ $qty }}</span>
                                            <button type="button" wire:click="step({{ $program->id }}, 1)" @disabled($qty >= $left) class="grid size-12 place-items-center rounded-full text-2xl text-white/80 disabled:opacity-30" aria-label="{{ __('ops.stamps.more') }}">+</button>
                                        </div>
                                        <button type="button" wire:click="add({{ $program->id }})" wire:loading.attr="disabled" wire:target="add({{ $program->id }})" @disabled($qty < 1)
                                                class="min-h-14 flex-1 rounded-full bg-status-ready px-5 text-base font-semibold text-ink-950 transition active:scale-[0.97] disabled:bg-white/10 disabled:text-white/40">
                                            {{ __('ops.stamps.add') }}
                                        </button>
                                    </div>
                                    <p class="mt-2 text-sm text-white/45">{{ trans_choice('ops.stamps.left_today', $left, ['count' => $left]) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </x-ops.shell>
</div>
