@php
    $field = 'min-h-14 w-full rounded-2xl border-0 bg-white/6 px-4 text-lg text-white ring-1 ring-white/10 placeholder:text-white/35 focus:ring-2 focus:ring-brass-300/60';
    $age = fn ($ticket): int => max(0, (int) $ticket->created_at->diffInMinutes(now(), true));
@endphp
<div wire:poll.30s>
    <x-ops.shell :branch="$branch" :title="__('ops.screens.pager')" :device-id="$deviceId" screen="pager" :channel="'branch.'.$branch->id.'.pager'">
        <div class="grid gap-5 p-4 sm:p-6 xl:grid-cols-[22rem_minmax(0,1fr)_minmax(0,1fr)]">

            {{-- New ticket --}}
            <section class="rounded-[2rem] bg-white/[0.045] p-1.5 ring-1 ring-white/8 xl:self-start" aria-labelledby="new-ticket">
                <form wire:submit="create" class="space-y-4 rounded-[calc(2rem-0.375rem)] bg-ink-900/60 p-5 ring-1 ring-white/5">
                    <h2 id="new-ticket" class="text-2xl font-semibold tracking-tight">{{ __('pager.counter.new') }}</h2>
                    <label class="block">
                        <span class="text-sm text-white/60">{{ __('pager.counter.label') }}</span>
                        <input wire:model="label" type="text" maxlength="60" placeholder="{{ __('pager.counter.label_hint') }}" class="mt-1.5 {{ $field }}">
                    </label>
                    <label class="block">
                        <span class="text-sm text-white/60">{{ __('pager.counter.phone') }}</span>
                        <input wire:model="phone" type="tel" inputmode="tel" maxlength="20" dir="ltr" placeholder="5555 1234" class="mt-1.5 text-start {{ $field }}">
                        <span class="mt-1.5 block text-xs text-white/45">{{ __('pager.counter.phone_hint') }}</span>
                    </label>
                    @error('phone')<p class="text-sm text-ice-100">{{ $message }}</p>@enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="create"
                            class="flex min-h-16 w-full items-center justify-center gap-3 rounded-full bg-brass-300 px-6 text-lg font-semibold text-ink-950 shadow-[0_18px_40px_-18px_color-mix(in_oklab,var(--color-brass-300)_80%,transparent)] transition active:scale-[0.98] disabled:opacity-60">
                        <svg wire:loading.remove wire:target="create" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                        <span wire:loading.remove wire:target="create">{{ __('pager.counter.create') }}</span>
                        <span wire:loading wire:target="create">{{ __('pager.counter.creating') }}</span>
                    </button>
                </form>
            </section>

            {{-- Preparing / Ready --}}
            @foreach ([['waiting', __('pager.counter.waiting'), $waiting, 'bg-status-progress', __('pager.counter.empty_waiting')], ['ready', __('pager.counter.ready'), $ready, 'bg-status-ready', __('pager.counter.empty_ready')]] as [$key, $heading, $tickets, $dot, $empty])
                <section aria-labelledby="col-{{ $key }}">
                    <h2 id="col-{{ $key }}" class="flex items-center gap-2.5 px-1 text-lg font-semibold text-white/80">
                        <span class="size-2.5 rounded-full {{ $dot }}"></span>{{ $heading }}
                        <span class="ms-auto rounded-full bg-white/8 px-3 py-0.5 text-base tabular-nums text-white/70">{{ $tickets->count() }}</span>
                    </h2>
                    @if ($tickets->isEmpty())
                        <p class="mt-3 rounded-[1.75rem] bg-white/[0.03] px-6 py-10 text-center text-white/45 ring-1 ring-white/6">{{ $empty }}</p>
                    @else
                        <ul class="mt-3 space-y-3">
                            @foreach ($tickets as $ticket)
                                <li wire:key="ticket-{{ $ticket->id }}" @class([
                                    'rounded-[1.75rem] p-1.5 ring-1 transition',
                                    'bg-white/[0.045] ring-white/8' => $key === 'waiting',
                                    'bg-status-ready/10 ring-status-ready/30' => $key === 'ready',
                                ])>
                                    <div class="flex flex-wrap items-center gap-4 rounded-[calc(1.75rem-0.375rem)] bg-ink-900/55 p-4">
                                        <span class="min-w-[4.5rem] text-center text-5xl font-semibold leading-none tabular-nums tracking-tight" dir="ltr">{{ $ticket->number }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-lg font-medium">{{ $ticket->label ?? __('pager.status.'.$ticket->status->value) }}</p>
                                            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/55">
                                                <span dir="ltr">{{ __('pager.counter.since', ['minutes' => $age($ticket)]) }}</span>
                                                <span @class(['inline-flex items-center gap-1.5', 'text-sage-100' => $ticket->opened_at, 'text-white/45' => ! $ticket->opened_at])>
                                                    <span @class(['size-1.5 rounded-full', 'bg-status-ready' => $ticket->opened_at, 'bg-white/30' => ! $ticket->opened_at])></span>
                                                    {{ $ticket->opened_at ? __('pager.counter.on_phone') : __('pager.counter.not_opened') }}
                                                </span>
                                                @if ($ticket->phone)<span>{{ __('pager.counter.with_message') }}</span>@endif
                                                @if ($key === 'ready')<span>{{ trans_choice('pager.counter.calls', $ticket->calls, ['count' => $ticket->calls]) }}</span>@endif
                                            </p>
                                        </div>
                                        <div class="flex w-full gap-2">
                                            @if ($key === 'waiting')
                                                <button type="button" wire:click="show({{ $ticket->id }})" class="min-h-14 shrink-0 rounded-full bg-white/6 px-5 text-base font-medium text-white/80 ring-1 ring-white/10 transition hover:bg-white/10">{{ __('pager.counter.show_qr') }}</button>
                                                <button type="button" wire:click="ready({{ $ticket->id }})" wire:loading.attr="disabled" wire:target="ready({{ $ticket->id }})"
                                                        class="min-h-14 flex-1 rounded-full bg-status-ready px-6 text-base font-semibold text-ink-950 transition active:scale-[0.97] disabled:opacity-60">{{ __('pager.counter.ready_action') }}</button>
                                            @else
                                                <button type="button" wire:click="callAgain({{ $ticket->id }})" wire:loading.attr="disabled" wire:target="callAgain({{ $ticket->id }})"
                                                        class="min-h-14 shrink-0 rounded-full bg-white/6 px-5 text-base font-medium text-white/85 ring-1 ring-white/10 transition hover:bg-white/10 disabled:opacity-60">{{ __('pager.counter.call_again') }}</button>
                                                <button type="button" wire:click="collected({{ $ticket->id }})" wire:loading.attr="disabled" wire:target="collected({{ $ticket->id }})"
                                                        class="min-h-14 flex-1 rounded-full bg-white px-6 text-base font-semibold text-ink-950 transition active:scale-[0.97] disabled:opacity-60">{{ __('pager.counter.collected') }}</button>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- The QR the guest scans --}}
        @if ($shown)
            <div class="fixed inset-0 z-40 grid place-items-center bg-ink-950/80 p-4 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="qr-title"
                 x-data x-init="$el.querySelector('[data-done]')?.focus()" x-on:keydown.escape.window="$wire.hide()">
                <div class="w-full max-w-md rounded-[2.25rem] bg-white/[0.06] p-2 ring-1 ring-white/10 motion-safe:animate-[pager-pop_420ms_cubic-bezier(0.32,0.72,0,1)]">
                    <div class="rounded-[calc(2.25rem-0.5rem)] bg-ink-900 p-6 text-center ring-1 ring-white/5">
                        <p class="text-sm uppercase tracking-[0.2em] text-white/50 rtl:tracking-normal">{{ __('pager.guest.number') }}</p>
                        <h2 id="qr-title" class="mt-1 text-7xl font-semibold leading-none tabular-nums tracking-tight" dir="ltr">{{ $shown->number }}</h2>
                        @if ($shown->label)<p class="mt-2 text-lg text-white/70">{{ $shown->label }}</p>@endif
                        <div class="mx-auto mt-5 w-64 max-w-full rounded-3xl bg-white p-4 [&_svg]:h-auto [&_svg]:w-full" aria-hidden="true">{!! $qr !!}</div>
                        <p class="mx-auto mt-4 max-w-xs text-base text-white/65">{{ __('pager.counter.scan_body') }}</p>
                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <button type="button" data-done wire:click="hide" class="min-h-14 rounded-full bg-white px-5 text-lg font-semibold text-ink-950 transition active:scale-[0.97]">{{ __('pager.counter.done') }}</button>
                            <button type="button" wire:click="ready({{ $shown->id }})" class="min-h-14 rounded-full bg-white/8 px-5 text-lg font-medium text-white ring-1 ring-white/10 transition hover:bg-white/12">{{ __('pager.counter.ready') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-ops.shell>

    <style>
        @keyframes pager-pop { from { opacity: 0; transform: translateY(12px) scale(0.97); } to { opacity: 1; transform: none; } }
    </style>
</div>
