@php
    $check = '<svg class="size-[46%]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12.5 4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $gift = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 12v8H4v-8M2 7h20v5H2zM12 20V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    // Stamps 4 and 5 land during the scene; the first three are already there.
    $lands = [4 => .2, 5 => .42];
@endphp
{{-- Rewards, told in motion: the waiter adds a stamp, then the last one, the Free circle lights up, and the gift lands in the wallet. --}}
<div class="bezel">
    <div class="bezel-core theme-fixed relative h-[25rem] overflow-hidden sm:h-[29rem] lg:h-[32rem]">
        <img src="{{ asset('images/site/saffron-karak.webp') }}" alt="" loading="lazy" width="900" height="675" class="absolute inset-0 size-full object-cover">
        <div class="absolute inset-0 bg-[linear-gradient(180deg,color-mix(in_oklab,var(--color-ink-950)_25%,transparent),color-mix(in_oklab,var(--color-ink-950)_72%,transparent))]"></div>

        {{-- +1, approved at the counter --}}
        <span class="absolute start-1/2 top-6 inline-flex -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full bg-white/95 py-1.5 pe-3.5 ps-1.5 text-[0.75rem] font-semibold text-ink-950 shadow-[var(--shadow-lift)] rtl:translate-x-1/2 sm:top-8" data-in data-out data-transient style="--at:.16; --until:.5">
            <span class="grid size-6 place-items-center rounded-full bg-sage-700 text-white"><svg class="size-3" viewBox="0 0 12 12" fill="none"><path d="M6 2.5v7M2.5 6h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
            {{ __('site.showcase.rewards.approved') }}
        </span>

        {{-- The card --}}
        <div class="absolute inset-x-4 top-[4.75rem] mx-auto max-w-sm sm:top-24">
            <div class="rounded-[1.75rem] p-1.5 ring-1 ring-white/15" style="background: color-mix(in oklab, var(--color-teal-800) 30%, transparent)">
                <div class="relative overflow-hidden rounded-[calc(1.75rem-0.375rem)] p-5 text-white shadow-[inset_0_1px_0_color-mix(in_oklab,white_18%,transparent)]" style="background: linear-gradient(140deg, color-mix(in oklab, var(--color-teal-800) 88%, white) 0%, var(--color-teal-800) 55%, color-mix(in oklab, var(--color-teal-800) 80%, var(--color-ink-950)) 100%)">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-14 -top-16 size-44 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-lg font-semibold tracking-tight">{{ __('site.showcase.rewards.card') }}</p>
                            <p class="mt-0.5 text-sm text-white/75">{{ __('site.showcase.rewards.line') }}</p>
                        </div>
                        <span class="grid shrink-0 rounded-full bg-white/15 px-2.5 py-1 text-xs font-semibold tabular-nums ring-1 ring-white/20" dir="ltr">
                            <span class="[grid-area:1/1]" data-early style="--until:.2">3/5</span>
                            <span class="[grid-area:1/1]" data-in data-out data-transient style="--at:.2; --until:.42">4/5</span>
                            <span class="[grid-area:1/1]" data-in style="--at:.42">5/5</span>
                        </span>
                    </div>

                    <ol class="relative mt-5 grid grid-cols-6 gap-2 sm:gap-2.5">
                        @for ($i = 1; $i <= 5; $i++)
                            <li class="grid aspect-square place-items-center rounded-full border-[1.5px] border-dashed border-white/45">
                                <span @class(['grid size-full place-items-center rounded-full bg-white text-teal-800 shadow-[0_6px_14px_-6px_rgb(10_15_28/0.35)] [grid-area:1/1]', 'sc-stamp' => isset($lands[$i])]) @isset($lands[$i]) style="--at:{{ $lands[$i] }}" @endisset>{!! $check !!}</span>
                            </li>
                        @endfor
                        {{-- The free one lights up when the card is full --}}
                        <li class="sc-free relative grid aspect-square place-items-center" style="--at:.54">
                            <span class="grid size-full place-items-center rounded-full bg-white/14 text-white ring-[1.5px] ring-white/60 [grid-area:1/1]" data-early style="--until:.54">
                                <span class="flex flex-col items-center gap-0.5 leading-none">{!! $gift !!}<span class="text-[0.5625rem] font-semibold">{{ __('site.showcase.rewards.free') }}</span></span>
                            </span>
                            <span class="grid size-full place-items-center rounded-full bg-brass-200 text-ink-950 shadow-[0_0_0_4px_color-mix(in_oklab,white_22%,transparent),0_10px_24px_-8px_rgb(10_15_28/0.45)] [grid-area:1/1]" data-pop style="--at:.54">
                                <span class="flex flex-col items-center gap-0.5 leading-none">{!! $gift !!}<span class="text-[0.5625rem] font-semibold">{{ __('site.showcase.rewards.free') }}</span></span>
                            </span>
                            @foreach ([['-1.4rem', '-1.6rem'], ['1.5rem', '-1.4rem'], ['-1.7rem', '0.6rem'], ['1.6rem', '0.9rem'], ['0rem', '-2rem']] as [$x, $y])
                                <span class="sc-spark pointer-events-none absolute size-1.5 rounded-full bg-brass-200" style="--at:.55; --sx:{{ $x }}; --sy:{{ $y }}"></span>
                            @endforeach
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        {{-- In the wallet --}}
        <div class="absolute inset-x-4 bottom-5 mx-auto max-w-sm sm:bottom-8" data-in style="--at:.64">
            <div class="flex items-center gap-3 rounded-[1.4rem] bg-white/95 p-3 shadow-[var(--shadow-float)] backdrop-blur-md">
                <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-brass-200 text-ink-950">{!! $gift !!}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-ink-950">{{ __('site.showcase.rewards.wallet') }}</p>
                    <p class="truncate text-xs text-ink-500">{{ __('site.showcase.rewards.wallet_hint') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
