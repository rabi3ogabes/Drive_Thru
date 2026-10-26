@php
    $restaurant = $ticket->branch->restaurant;
    $branchName = $ticket->branch->name;
    $state = $status;
@endphp
<div class="relative isolate flex min-h-dvh flex-col overflow-hidden"
     @if ($live) data-live-channel="pager.{{ $ticket->token }}" data-live-auth="{{ route('realtime.auth') }}" x-on:live-update.debounce.100ms="$wire.$refresh()" wire:poll.10s.visible @endif
     x-data="pagerRinger({ readyTitle: {{ \Illuminate\Support\Js::from(__('pager.guest.ready_title')) }} })">
    @vite('resources/js/pager.js')

    {{-- Backdrop: the restaurant's colour while waiting, a bright calm ivory when ready --}}
    <div aria-hidden="true" @class([
        'absolute inset-0 -z-10 transition-colors duration-700 ease-[cubic-bezier(0.32,0.72,0,1)]',
        'bg-[var(--brand)]' => $state === 'waiting',
        'bg-ivory-50' => $state !== 'waiting',
    ])></div>
    @if ($state === 'waiting')
        <div aria-hidden="true" class="pointer-events-none absolute -end-24 -top-28 -z-10 size-80 rounded-full bg-white/10 blur-3xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -start-20 -z-10 size-96 rounded-full bg-black/10 blur-3xl"></div>
    @endif

    <header @class(['flex items-center gap-3 px-5 pt-[max(1.25rem,env(safe-area-inset-top))]', 'text-white' => $state === 'waiting', 'text-ink-900' => $state !== 'waiting'])>
        @if ($logo = $restaurant->getFilamentAvatarUrl())
            <img src="{{ $logo }}" alt="" class="size-10 rounded-2xl bg-white object-cover ring-1 ring-black/5">
        @else
            <span @class(['grid size-10 place-items-center rounded-2xl text-sm font-semibold ring-1', 'bg-white/15 ring-white/20' => $state === 'waiting', 'bg-white ring-ink-950/5' => $state !== 'waiting'])>{{ $restaurant->initials() }}</span>
        @endif
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold">{{ $restaurant->name }}</p>
            <p @class(['truncate text-xs', 'text-white/70' => $state === 'waiting', 'text-ink-500' => $state !== 'waiting'])>{{ $branchName }}</p>
        </div>
    </header>

    <main id="main" class="flex flex-1 flex-col items-center justify-center px-5 py-10 text-center">
        {{-- The number, inside a breathing halo while waiting and a ringing one when ready --}}
        <div class="relative grid place-items-center">
            @if ($state === 'waiting')
                <span aria-hidden="true" class="absolute size-64 rounded-full bg-white/10 motion-safe:animate-[pager-breathe_3.2s_ease-in-out_infinite]"></span>
                <span aria-hidden="true" class="absolute size-80 rounded-full bg-white/[0.06] motion-safe:animate-[pager-breathe_3.2s_ease-in-out_0.4s_infinite]"></span>
            @elseif ($state === 'ready')
                <span aria-hidden="true" class="absolute size-64 rounded-full bg-status-ready/25 motion-safe:animate-[pager-ring_1.4s_cubic-bezier(0.32,0.72,0,1)_infinite]"></span>
                <span aria-hidden="true" class="absolute size-64 rounded-full bg-status-ready/20 motion-safe:animate-[pager-ring_1.4s_cubic-bezier(0.32,0.72,0,1)_0.45s_infinite]"></span>
            @endif

            <div @class([
                'relative rounded-[2.75rem] p-2 ring-1',
                'bg-white/12 ring-white/20' => $state === 'waiting',
                'bg-status-ready/15 ring-status-ready/30' => $state === 'ready',
                'bg-white ring-ink-950/5' => in_array($state, ['collected', 'expired'], true),
            ])>
                <div @class([
                    'grid min-w-48 place-items-center rounded-[calc(2.75rem-0.5rem)] px-10 py-8',
                    'bg-white text-ink-950 shadow-[0_30px_60px_-30px_color-mix(in_oklab,var(--color-ink-950)_45%,transparent)]' => $state === 'waiting',
                    'bg-white text-ink-950 shadow-[0_30px_70px_-25px_color-mix(in_oklab,var(--color-status-ready)_75%,transparent)]' => $state === 'ready',
                    'bg-ivory-100 text-ink-400' => in_array($state, ['collected', 'expired'], true),
                ])>
                    <span class="text-xs font-medium uppercase tracking-[0.22em] text-ink-400 rtl:tracking-normal">{{ __('pager.guest.number') }}</span>
                    <span class="mt-1 text-[5.5rem] font-semibold leading-none tabular-nums tracking-tight" dir="ltr">{{ $ticket->number }}</span>
                </div>
            </div>
        </div>

        <div class="mt-10 max-w-sm" aria-live="assertive">
            @switch($state)
                @case('waiting')
                    <h1 class="display text-[2rem] leading-tight text-white">{{ __('pager.guest.waiting_title') }}</h1>
                    <p class="mt-3 text-base text-white/80">{{ __('pager.guest.waiting_body') }}</p>
                    @break
                @case('ready')
                    <h1 class="display text-[2.25rem] leading-tight text-ink-950">{{ __('pager.guest.ready_title') }}</h1>
                    <p class="mt-3 text-lg text-ink-700">{{ __('pager.guest.ready_body', ['number' => $ticket->number]) }}</p>
                    @break
                @case('collected')
                    <h1 class="display text-[2rem] leading-tight text-ink-950">{{ __('pager.guest.collected_title') }}</h1>
                    <p class="mt-3 text-base text-ink-600">{{ __('pager.guest.collected_body', ['restaurant' => $restaurant->name]) }}</p>
                    @break
                @default
                    <h1 class="display text-[2rem] leading-tight text-ink-950">{{ __('pager.guest.expired_title') }}</h1>
                    <p class="mt-3 text-base text-ink-600">{{ __('pager.guest.expired_body') }}</p>
            @endswitch

            @if ($ticket->label && in_array($state, ['waiting', 'ready'], true))
                <p @class(['mt-5 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm', 'bg-white/12 text-white ring-1 ring-white/15' => $state === 'waiting', 'bg-white text-ink-800 ring-1 ring-ink-950/5' => $state === 'ready'])>
                    <span class="opacity-70">{{ __('pager.guest.what') }}</span><span class="font-semibold">{{ $ticket->label }}</span>
                </p>
            @endif
        </div>
    </main>

    {{-- Sound and "on my way" --}}
    @if (in_array($state, ['waiting', 'ready'], true))
        <footer class="px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            @if ($state === 'ready')
                <button type="button" x-on:click="stop()" x-show="ringing" x-transition.opacity
                        class="mx-auto flex min-h-14 w-full max-w-sm items-center justify-center rounded-full bg-ink-950 px-6 text-lg font-semibold text-white shadow-[0_20px_40px_-20px_color-mix(in_oklab,var(--color-ink-950)_60%,transparent)] transition active:scale-[0.98]">
                    {{ __('pager.guest.stop') }}
                </button>
            @endif
            <button type="button" x-on:click="enableSound()" x-show="! sound"
                    @class(['mx-auto mt-3 flex min-h-12 w-full max-w-sm items-center justify-center gap-2 rounded-full px-5 text-sm font-medium ring-1 transition active:scale-[0.98]',
                        'bg-white/12 text-white ring-white/20' => $state === 'waiting',
                        'bg-white text-ink-800 ring-ink-950/8' => $state === 'ready'])>
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('pager.guest.sound_on') }}
            </button>
            <p x-show="sound" x-cloak @class(['mt-3 flex items-center justify-center gap-2 text-xs', 'text-white/70' => $state === 'waiting', 'text-ink-500' => $state === 'ready'])>
                <span class="size-1.5 rounded-full bg-status-ready"></span>{{ __('pager.guest.sound_ready') }} · {{ __('pager.guest.keep_awake') }}
            </p>
        </footer>
    @endif

    <style>
        @keyframes pager-breathe { 0%, 100% { transform: scale(0.92); opacity: 0.55; } 50% { transform: scale(1.04); opacity: 1; } }
        @keyframes pager-ring { from { transform: scale(0.85); opacity: 0.9; } to { transform: scale(1.6); opacity: 0; } }
    </style>
</div>
