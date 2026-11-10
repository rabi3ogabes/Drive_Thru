{{-- Order-Ready Call, told in motion: the guest waits anywhere, the phone rings and buzzes, WhatsApp follows, and 27 moves to "Ready" on the TV. --}}
<div class="bezel">
    <div class="bezel-core theme-fixed relative h-[25rem] overflow-hidden bg-ink-950 bg-[radial-gradient(90%_70%_at_20%_15%,color-mix(in_oklab,var(--color-teal-700)_55%,transparent),transparent_60%)] sm:h-[29rem] lg:h-[32rem]">

        {{-- The guest's phone, their pager --}}
        <div class="absolute start-4 top-5 w-[9.75rem] sm:start-8 sm:top-7 sm:w-[12.5rem]">
            <div class="sc-buzz rounded-[2.2rem] bg-ink-900 p-1.5 shadow-[0_40px_80px_-30px_rgb(0_0_0/0.7)] ring-1 ring-white/10" style="--at:.46">
                <div class="relative h-[20.5rem] overflow-hidden rounded-[1.85rem] sm:h-[24rem]">
                    {{-- Waiting --}}
                    <div class="absolute inset-0 flex flex-col items-center bg-teal-900 px-3 pt-10 text-center text-white" data-early style="--until:.46">
                        <p class="text-[0.625rem] font-semibold uppercase tracking-[0.16em] text-white/60 rtl:tracking-normal">{{ __('services.names.ready_call') }}</p>
                        <div class="relative mt-7 grid size-28 place-items-center sm:size-32">
                            <span class="sc-breathe absolute inset-0 rounded-full bg-white/10"></span>
                            <span class="relative text-5xl font-semibold tabular-nums sm:text-6xl" dir="ltr">27</span>
                        </div>
                        <p class="mt-7 text-sm font-semibold">{{ __('site.showcase.ready_call.waiting') }}</p>
                        <p class="mt-1 text-[0.6875rem] leading-snug text-white/60">{{ __('site.showcase.ready_call.waiting_hint') }}</p>
                    </div>
                    {{-- Ready: rings, bright, can't be missed --}}
                    <div class="absolute inset-0 flex flex-col items-center bg-ivory-50 px-3 pt-10 text-center" data-in style="--at:.46">
                        <p class="text-[0.625rem] font-semibold uppercase tracking-[0.16em] text-ink-400 rtl:tracking-normal">{{ __('services.names.ready_call') }}</p>
                        <div class="relative mt-7 grid size-28 place-items-center sm:size-32">
                            <span class="sc-ring absolute inset-0 rounded-full bg-status-ready/25"></span>
                            <span class="sc-ring absolute inset-0 rounded-full bg-status-ready/20" style="animation-delay:.5s"></span>
                            <span class="relative grid size-24 place-items-center rounded-[1.6rem] bg-white shadow-[var(--shadow-lift)] ring-1 ring-status-ready/30 sm:size-28">
                                <span class="text-5xl font-semibold tabular-nums text-ink-950 sm:text-6xl" dir="ltr">27</span>
                            </span>
                        </div>
                        <p class="display mt-7 text-lg leading-tight text-ink-950 sm:text-xl">{{ __('site.showcase.ready_call.ready') }}</p>
                        <p class="mt-1 text-[0.6875rem] text-ink-500">{{ __('site.showcase.ready_call.ready_hint') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- The waiting-area TV --}}
        <div class="absolute end-3 top-6 w-[10rem] sm:end-8 sm:top-8 sm:w-[13.5rem]">
            <div class="rounded-[1.4rem] bg-white/[0.06] p-1.5 ring-1 ring-white/10">
                <div class="grid grid-cols-2 gap-1.5 rounded-[1.05rem] bg-ink-900 p-2.5">
                    <div>
                        <p class="flex items-center gap-1.5 text-[0.5625rem] font-semibold text-white/60"><span class="size-1.5 rounded-full bg-status-progress"></span>{{ __('pager.board.preparing') }}</p>
                        <ul class="mt-2 grid gap-1.5">
                            <li class="rounded-lg bg-white/[0.06] py-1.5 text-center text-sm font-semibold tabular-nums text-white/80" data-early style="--until:.45" dir="ltr">27</li>
                            @foreach ([31, 32] as $n)
                                <li class="rounded-lg bg-white/[0.06] py-1.5 text-center text-sm font-semibold tabular-nums text-white/80" dir="ltr">{{ $n }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="flex items-center gap-1.5 text-[0.5625rem] font-semibold text-sage-100"><span class="size-1.5 rounded-full bg-status-ready"></span>{{ __('pager.board.ready') }}</p>
                        <ul class="mt-2 grid gap-1.5">
                            <li class="rounded-lg bg-ivory-50 py-1.5 text-center text-sm font-semibold tabular-nums text-ink-950 shadow-[0_0_0_3px_color-mix(in_oklab,var(--color-status-ready)_35%,transparent)]" data-pop style="--at:.47" dir="ltr">27</li>
                            @foreach ([25, 26] as $n)
                                <li class="rounded-lg bg-status-ready/15 py-1.5 text-center text-sm font-semibold tabular-nums text-sage-100" dir="ltr">{{ $n }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- WhatsApp, as a backup --}}
        <div class="absolute bottom-6 end-4 w-[10.75rem] sm:bottom-8 sm:end-8 sm:w-[14rem]" data-in style="--at:.58">
            <div class="rounded-[1.25rem] rounded-ss-md bg-white p-3 shadow-[0_30px_60px_-24px_rgb(0_0_0/0.6)]">
                <p class="flex items-center gap-1.5 text-[0.625rem] font-semibold text-ink-500">
                    <span class="grid size-4 place-items-center rounded-full bg-status-ready text-white"><svg class="size-2.5" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.5a6.5 6.5 0 0 0-5.6 9.8L1.5 14.5l3.3-.9A6.5 6.5 0 1 0 8 1.5Z"/></svg></span>
                    WhatsApp · {{ __('site.showcase.ready_call.now') }}
                </p>
                <p class="mt-1.5 text-[0.75rem] leading-snug text-ink-800">{{ __('site.showcase.ready_call.whatsapp') }}</p>
            </div>
        </div>
    </div>
</div>
