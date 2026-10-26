@php
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
@endphp
{{-- A service, shown working: a photo or a screen, never a stock icon. --}}
<div {{ $attributes ?? '' }} class="bezel">
    <div class="bezel-core theme-fixed relative h-[22rem] overflow-hidden sm:h-[26rem]">
        @if ($key === 'drive_thru')
            <img src="{{ asset('images/site/zaatar-manakish.webp') }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover" width="900" height="675">
            <div class="absolute inset-0 bg-[linear-gradient(180deg,transparent_35%,color-mix(in_oklab,var(--color-ink-950)_70%,transparent))]"></div>
            <div class="absolute inset-x-5 bottom-5 flex items-center gap-3 rounded-[1.4rem] bg-white/92 p-3 shadow-[var(--shadow-lift)] backdrop-blur-md">
                <img src="{{ asset('images/site/karak-tea.webp') }}" alt="" loading="lazy" class="size-14 rounded-2xl object-cover" width="56" height="56">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-ink-950">{{ __('site.stage.order') }}</p>
                    <p class="mt-0.5 text-sm text-ink-500">{{ __('site.stage.lane') }} · {{ __('site.stage.arriving') }}</p>
                </div>
                <span class="grid size-10 place-items-center rounded-full bg-teal-900 text-brass-200"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 17h14M6 17l1.5-5h9L18 17M8 12l1-3h6l1 3" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="17.5" r="1.5"/><circle cx="16" cy="17.5" r="1.5"/></svg></span>
            </div>
            <span class="absolute start-5 top-5 inline-flex items-center gap-2 rounded-full bg-white/90 px-3.5 py-1.5 text-xs font-semibold text-ink-900 shadow-sm backdrop-blur"><span class="size-1.5 rounded-full bg-status-ready motion-safe:animate-pulse"></span>{{ __('services.names.drive_thru') }}</span>
        @elseif ($key === 'ready_call')
            <div class="absolute inset-0 bg-ink-950 p-5 sm:p-7">
                <div class="grid h-full grid-cols-[1fr_1.35fr] gap-3">
                    <div class="rounded-[1.4rem] bg-white/[0.05] p-4 ring-1 ring-white/10">
                        <p class="flex items-center gap-2 text-sm font-semibold text-white/70"><span class="size-2 rounded-full bg-status-progress"></span>{{ __('pager.board.preparing') }}</p>
                        <ul class="mt-4 grid grid-cols-2 gap-2">
                            @foreach ([31, 32, 33, 34] as $n)
                                <li class="rounded-2xl bg-white/[0.06] py-3 text-center text-2xl font-semibold tabular-nums text-white/80 ring-1 ring-white/10" dir="ltr">{{ $n }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex flex-col rounded-[1.4rem] bg-status-ready/[0.08] p-4 ring-1 ring-status-ready/25">
                        <p class="flex items-center gap-2 text-sm font-semibold text-sage-100"><span class="size-2 rounded-full bg-status-ready"></span>{{ __('pager.board.ready') }}</p>
                        <div class="mt-4 flex items-center justify-between rounded-[1.2rem] bg-ivory-50 px-5 py-4 motion-safe:animate-[site-glow_2.4s_ease-in-out_infinite]">
                            <span class="text-xs font-semibold text-ink-500">{{ __('pager.board.latest') }}</span>
                            <span class="text-6xl font-semibold leading-none tabular-nums text-ink-950" dir="ltr">27</span>
                        </div>
                        <ul class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ([25, 26] as $n)
                                <li class="rounded-2xl bg-status-ready/15 py-3 text-center text-2xl font-semibold tabular-nums text-sage-100 ring-1 ring-status-ready/30" dir="ltr">{{ $n }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @else
            <img src="{{ asset('images/site/kunafa.webp') }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover" width="900" height="675">
            <div class="absolute inset-0 bg-[linear-gradient(200deg,transparent_25%,color-mix(in_oklab,var(--color-ink-950)_60%,transparent))]"></div>
            <div class="absolute inset-x-5 bottom-5 sm:end-auto sm:w-80">
                <x-stamp-card :name="__('site.stage.dessert')" :needed="5" :filled="4" accent="#5a6d59" class="shadow-[var(--shadow-float)]" />
            </div>
            <span class="absolute end-5 top-5 inline-flex items-center gap-2 rounded-full bg-white/92 py-1.5 pe-4 ps-1.5 text-sm font-semibold text-ink-900 shadow-[var(--shadow-lift)] backdrop-blur motion-safe:animate-[site-float_6s_ease-in-out_infinite]">
                <span class="grid size-7 place-items-center rounded-full bg-sage-700 text-white">{!! $check !!}</span>{{ __('site.stage.plus_one') }}
            </span>
        @endif
    </div>
</div>
