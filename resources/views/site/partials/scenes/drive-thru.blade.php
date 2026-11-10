@php
    $price = \App\Support\Money::format(1200, 'QAR');
@endphp
{{-- Drive-Thru, told in motion: tap and pay in the car, the kitchen ticket moves to ready, the car rolls up to the window and away. --}}
<div class="bezel">
    <div class="bezel-core theme-fixed relative h-[25rem] overflow-hidden bg-[radial-gradient(120%_90%_at_80%_0%,var(--color-brass-100),transparent_55%),linear-gradient(180deg,var(--color-ivory-50),var(--color-ivory-100))] sm:h-[29rem] lg:h-[32rem]">

        {{-- The guest's phone --}}
        <div class="absolute start-3 top-5 w-[9.5rem] sm:start-8 sm:top-7 sm:w-[13rem]">
            <div class="rounded-[2.2rem] bg-ink-950 p-1.5 shadow-[var(--shadow-float)] ring-1 ring-ink-950/40">
                <div class="relative overflow-hidden rounded-[1.85rem] bg-ivory-50 pb-3">
                    <div class="flex items-center justify-between px-4 pt-3 text-[0.5625rem] font-semibold text-ink-500"><span dir="ltr">9:41</span><span class="h-3.5 w-12 rounded-full bg-ink-950"></span><span class="h-1.5 w-3 rounded-sm bg-ink-400"></span></div>
                    <div class="px-3 pt-3">
                        <img src="{{ asset('images/site/karak-tea.webp') }}" alt="" loading="lazy" width="320" height="200" class="h-20 w-full rounded-2xl object-cover sm:h-24">
                        <div class="mt-2.5 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-[0.8125rem] font-semibold text-ink-950">{{ __('site.showcase.drive_thru.item') }}</p>
                                <p class="truncate text-[0.625rem] text-ink-500">{{ __('site.showcase.drive_thru.item_note') }}</p>
                            </div>
                            {{-- Add: tapped, then ticked --}}
                            <span class="sc-press relative grid h-7 min-w-11 shrink-0 place-items-center rounded-full bg-teal-900 px-2.5 text-[0.625rem] font-semibold text-white" style="--at:.1">
                                <span class="sc-tap pointer-events-none absolute inset-0 rounded-full bg-teal-700" style="--at:.1; opacity:0"></span>
                                <span class="[grid-area:1/1]" data-early style="--until:.12">{{ __('site.showcase.drive_thru.add') }}</span>
                                <svg class="size-3.5 [grid-area:1/1]" data-in style="--at:.12" viewBox="0 0 12 12" fill="none"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        </div>
                        {{-- Pay --}}
                        <div class="sc-press mt-3 flex items-center justify-between rounded-full bg-ink-950 py-2 pe-2 ps-3.5 text-[0.6875rem] font-semibold text-white" style="--at:.2">
                            <span>{{ __('site.showcase.drive_thru.pay') }}</span>
                            <span class="rounded-full bg-white/12 px-2 py-0.5 tabular-nums" dir="ltr">{{ $price }}</span>
                        </div>
                    </div>
                    {{-- Paid --}}
                    <div class="absolute inset-x-2 bottom-2 flex items-center gap-2 rounded-2xl bg-white px-3 py-2.5 shadow-[var(--shadow-lift)] ring-1 ring-ink-950/5" data-in style="--at:.25">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-status-ready text-white"><svg class="size-3" viewBox="0 0 12 12" fill="none"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="text-[0.6875rem] font-semibold leading-tight text-ink-950">{{ __('site.showcase.drive_thru.paid') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- The kitchen ticket --}}
        <div class="absolute end-3 top-24 w-[9.25rem] sm:end-8 sm:top-24 sm:w-[12.5rem]" data-in style="--at:.28">
            <div class="rounded-[1.5rem] bg-white/70 p-1.5 shadow-[var(--shadow-float)] ring-1 ring-ink-950/5 backdrop-blur-md">
                <div class="rounded-[1.15rem] bg-white p-3 sm:p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[0.625rem] font-semibold uppercase tracking-[0.16em] text-ink-400 rtl:tracking-normal">{{ __('site.showcase.drive_thru.kitchen') }}</p>
                        <p class="font-mono text-xs font-semibold text-ink-950" dir="ltr">A582</p>
                    </div>
                    <p class="mt-2.5 text-[0.8125rem] font-semibold text-ink-950"><span class="tabular-nums" dir="ltr">1 ×</span> {{ __('site.showcase.drive_thru.item') }}</p>
                    <p class="text-[0.6875rem] text-ink-500">{{ __('site.showcase.drive_thru.item_note') }}</p>
                    <div class="mt-3 grid">
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-status-new/10 px-2.5 py-1 text-[0.6875rem] font-semibold text-status-new [grid-area:1/1]" data-early style="--until:.4"><span class="size-1.5 rounded-full bg-status-new"></span>{{ __('site.showcase.drive_thru.new') }}</span>
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-status-progress/12 px-2.5 py-1 text-[0.6875rem] font-semibold text-ink-800 [grid-area:1/1]" data-in data-out data-transient style="--at:.4; --until:.62"><span class="size-1.5 rounded-full bg-status-progress"></span>{{ __('site.showcase.drive_thru.preparing') }}</span>
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-status-ready/12 px-2.5 py-1 text-[0.6875rem] font-semibold text-ink-800 [grid-area:1/1]" data-in style="--at:.62"><span class="size-1.5 rounded-full bg-status-ready"></span>{{ __('site.showcase.drive_thru.ready') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- The lane: the car rolls up to the window, then away --}}
        <div class="absolute inset-x-0 bottom-0 h-[5.5rem] bg-ink-950 sm:h-24">
            <div class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-[repeating-linear-gradient(90deg,color-mix(in_oklab,white_35%,transparent)_0_14px,transparent_14px_28px)]"></div>
            {{-- The window --}}
            <div class="absolute bottom-0 end-4 top-0 flex w-14 flex-col items-center justify-center gap-1 border-s border-white/10 bg-white/[0.04] sm:end-8 sm:w-16">
                <span class="h-6 w-8 rounded-md bg-brass-300/80 shadow-[0_0_18px_color-mix(in_oklab,var(--color-brass-300)_60%,transparent)]"></span>
                <span class="text-[0.5625rem] font-semibold uppercase tracking-[0.14em] text-white/50 rtl:tracking-normal">{{ __('site.showcase.drive_thru.window') }}</span>
            </div>
            <div class="sc-car absolute bottom-3 end-[5.5rem] w-24 sm:end-28 sm:w-28">
                <svg class="flip-rtl w-full text-ivory-100" viewBox="0 0 120 48" fill="none" aria-hidden="true">
                    <path d="M8 34c0-5 3-8 8-9l14-3 13-11c3-2 6-3 10-3h22c4 0 7 1 10 4l11 10 10 2c5 1 8 4 8 9v4c0 2-2 4-4 4H12c-2 0-4-2-4-4v-3Z" fill="currentColor"/>
                    <path d="M47 13h14v11H36l7-8c1-2 2-3 4-3Zm19 0h12c2 0 4 1 5 2l8 9H66V13Z" fill="var(--color-ink-800)"/>
                    <circle cx="31" cy="40" r="7" fill="var(--color-ink-950)" stroke="var(--color-ink-500)" stroke-width="3"/>
                    <circle cx="91" cy="40" r="7" fill="var(--color-ink-950)" stroke="var(--color-ink-500)" stroke-width="3"/>
                    <path d="M108 29h4" stroke="var(--color-brass-300)" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="absolute bottom-[4.5rem] end-[5.5rem] inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-white px-2.5 py-1 text-[0.6875rem] font-semibold text-ink-950 shadow-[var(--shadow-lift)] sm:bottom-[5.25rem] sm:end-28" data-in data-out style="--at:.6; --until:.84">
                <span class="size-1.5 rounded-full bg-status-ready"></span>{{ __('site.showcase.drive_thru.arrived') }}
            </span>
        </div>
    </div>
</div>
