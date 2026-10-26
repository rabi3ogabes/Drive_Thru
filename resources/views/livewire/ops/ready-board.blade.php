<div wire:poll.20s>
    <x-ops.shell :branch="$branch" :title="__('pager.board.title')" :device-id="$deviceId" screen="ready" :channel="'branch.'.$branch->id.'.pager'">
        <div class="grid min-h-[calc(100dvh-5.5rem)] gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)] lg:p-8">

            {{-- Preparing --}}
            <section class="flex flex-col rounded-[2.25rem] bg-white/[0.035] p-6 ring-1 ring-white/8 lg:p-8" aria-labelledby="board-preparing">
                <h2 id="board-preparing" class="flex items-center gap-3 text-[clamp(1.5rem,2.4vw,2.5rem)] font-semibold text-white/75">
                    <span class="size-3 rounded-full bg-status-progress motion-safe:animate-pulse"></span>{{ __('pager.board.preparing') }}
                </h2>
                @if ($preparing->isEmpty())
                    <p class="m-auto text-[clamp(1.25rem,1.8vw,1.75rem)] text-white/35">{{ __('pager.board.empty_preparing') }}</p>
                @else
                    <ul class="mt-6 grid grid-cols-3 gap-3 xl:grid-cols-4" role="list">
                        @foreach ($preparing->take(24) as $ticket)
                            <li wire:key="prep-{{ $ticket->id }}" class="rounded-3xl bg-white/[0.05] py-4 text-center text-[clamp(2rem,3.6vw,3.75rem)] font-semibold leading-none tabular-nums text-white/80 ring-1 ring-white/8" dir="ltr">{{ $ticket->number }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Ready --}}
            <section class="flex flex-col rounded-[2.25rem] bg-status-ready/[0.07] p-6 ring-1 ring-status-ready/25 lg:p-8" aria-labelledby="board-ready" aria-live="polite">
                <h2 id="board-ready" class="flex items-center gap-3 text-[clamp(1.5rem,2.4vw,2.5rem)] font-semibold text-sage-100">
                    <span class="size-3 rounded-full bg-status-ready"></span>{{ __('pager.board.ready') }}
                </h2>
                @if ($latest)
                    <div wire:key="latest-{{ $latest->id }}" class="mt-6 rounded-[2rem] bg-status-ready/30 p-1.5 ring-1 ring-status-ready/50 shadow-[0_30px_80px_-30px_color-mix(in_oklab,var(--color-status-ready)_70%,transparent)] motion-safe:animate-[board-in_700ms_cubic-bezier(0.32,0.72,0,1)]">
                        <div class="flex items-center justify-between gap-6 rounded-[calc(2rem-0.375rem)] bg-ivory-50 px-8 py-6 text-ink-950 shadow-[inset_0_1px_0_color-mix(in_oklab,var(--color-ivory-50)_60%,transparent)]">
                            <span class="flex items-center gap-3 text-[clamp(1.25rem,1.8vw,1.75rem)] font-semibold text-ink-600"><span class="size-3 rounded-full bg-status-ready motion-safe:animate-pulse"></span>{{ __('pager.board.latest') }}</span>
                            <span class="text-[clamp(5rem,11vw,10rem)] font-semibold leading-none tabular-nums tracking-tight" dir="ltr">{{ $latest->number }}</span>
                        </div>
                    </div>
                    <ul class="mt-5 grid grid-cols-3 gap-3 xl:grid-cols-4" role="list">
                        @foreach ($ready->skip(1)->take(16) as $ticket)
                            <li wire:key="ready-{{ $ticket->id }}" class="rounded-3xl bg-status-ready/15 py-4 text-center text-[clamp(2rem,3.6vw,3.75rem)] font-semibold leading-none tabular-nums text-sage-100 ring-1 ring-status-ready/30" dir="ltr">{{ $ticket->number }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="m-auto text-[clamp(1.25rem,1.8vw,1.75rem)] text-white/35">{{ __('pager.board.empty_ready') }}</p>
                @endif
                <p class="mt-auto pt-6 text-[clamp(1rem,1.3vw,1.375rem)] text-white/55">{{ __('pager.board.hint') }}</p>
            </section>
        </div>
    </x-ops.shell>

    <style>
        @keyframes board-in { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: none; } }
    </style>
</div>
