@php
    $slides = ['drive_thru' => 'drive-thru', 'ready_call' => 'ready-call', 'rewards' => 'rewards'];
    $count = count($slides);
@endphp
{{-- The three services: one slide each, every slide a short scene in motion (resources/js/showcase.js). --}}
<section id="services" class="showcase scroll-mt-28 px-4 py-20 sm:px-6 lg:py-28"
         x-data="serviceShowcase({{ $count }})" :class="{ 'is-paused': paused }"
         aria-roledescription="carousel" aria-label="{{ __('site.showcase.label') }}">
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between" data-reveal>
            <div class="max-w-2xl">
                <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.services.eyebrow') }}</p>
                <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('site.services.title') }}</h2>
                <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __('site.services.lead') }}</p>
            </div>
            {{-- Pause / play (anything that moves on its own can be stopped) --}}
            <div class="flex items-center gap-3 text-sm text-ink-500" x-show="!still" x-cloak>
                <span class="tabular-nums" dir="ltr" aria-hidden="true"><span x-text="String(active + 1).padStart(2, '0')">01</span> / {{ str_pad((string) $count, 2, '0', STR_PAD_LEFT) }}</span>
                <button type="button" @click="toggle()"
                        class="grid size-11 place-items-center rounded-full bg-white/70 text-ink-950 ring-1 ring-ink-950/10 backdrop-blur transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-white active:scale-95"
                        :aria-label="stopped ? @js(__('site.showcase.play')) : @js(__('site.showcase.pause'))" :aria-pressed="stopped ? 'true' : 'false'">
                    <svg x-show="!stopped" class="size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="4" y="3" width="2.6" height="10" rx="1"/><rect x="9.4" y="3" width="2.6" height="10" rx="1"/></svg>
                    <svg x-show="stopped" x-cloak class="flip-rtl size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 3.4v9.2a.8.8 0 0 0 1.2.7l7.2-4.6a.8.8 0 0 0 0-1.4L6.2 2.7A.8.8 0 0 0 5 3.4Z"/></svg>
                </button>
            </div>
        </div>

        {{-- Tabs: one per service, each with the line that fills while it plays --}}
        <div class="mt-12 grid grid-cols-3 gap-2 sm:gap-6 lg:mt-16" role="tablist" aria-label="{{ __('site.services.eyebrow') }}" @keydown="key($event)" data-reveal style="--i:1">
            @foreach (array_keys($slides) as $index => $key)
                <button type="button" role="tab" id="showcase-tab-{{ $key }}" aria-controls="showcase-panel-{{ $key }}" x-ref="tab{{ $index }}"
                        :aria-selected="active === {{ $index }} ? 'true' : 'false'" :tabindex="active === {{ $index }} ? 0 : -1"
                        @click="show({{ $index }}, { user: true })"
                        class="group rounded-2xl px-1 pb-3 pt-1 text-start outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-4 focus-visible:ring-offset-ivory-50"
                        @if ($index === 0) aria-selected="true" tabindex="0" @else aria-selected="false" tabindex="-1" @endif>
                    <span class="flex items-baseline gap-2.5">
                        <span class="hidden text-xs font-semibold tabular-nums transition-colors duration-500 sm:inline" :class="active === {{ $index }} ? 'text-brass-700' : 'text-ink-400'" dir="ltr">0{{ $index + 1 }}</span>
                        <span class="text-[0.8125rem] font-semibold leading-tight transition-colors duration-500 sm:text-base" :class="active === {{ $index }} ? 'text-ink-950' : 'text-ink-500 group-hover:text-ink-800'">{{ __("site.services.{$key}.kicker") }}</span>
                    </span>
                    <span class="sc-progress mt-3 block" x-ref="bar{{ $index }}" @animationend="ended($event, {{ $index }})"
                          :class="{ 'is-done': still ? active === {{ $index }} : active > {{ $index }} }"></span>
                </button>
            @endforeach
        </div>

        {{-- Slides --}}
        <div class="sc-slides mt-10 lg:mt-12" x-ref="stage" :aria-live="paused ? 'polite' : 'off'"
             @mouseenter="hovered = true" @mouseleave="hovered = false"
             @focusin="focused = true" @focusout="focused = $el.contains($event.relatedTarget)"
             @pointerdown="swipeStart($event)" @pointerup="swipeEnd($event)" @pointercancel="touch = null"
             style="touch-action: pan-y">
            @foreach ($slides as $key => $slug)
                @php $service = $services[$key]; $index = $loop->index; @endphp
                <article id="showcase-panel-{{ $key }}" role="tabpanel" aria-labelledby="showcase-tab-{{ $key }}" aria-roledescription="slide"
                         aria-label="{{ __('site.showcase.slide', ['current' => $index + 1, 'total' => $count]) }}"
                         @class(['sc-slide grid items-center gap-8 lg:grid-cols-12 lg:gap-14', 'is-active' => $index === 0])
                         :class="{ 'is-active': active === {{ $index }} }" :inert="active !== {{ $index }}" @if ($index !== 0) inert @endif>
                    {{-- The scene --}}
                    <div class="sc-scene lg:col-span-7" x-ref="scene{{ $index }}" aria-hidden="true">
                        @include('site.partials.scenes.'.$slug)
                    </div>

                    {{-- The words --}}
                    <div class="lg:col-span-5">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brass-700 rtl:tracking-normal">{{ __("site.services.{$key}.kicker") }}</p>
                        <h3 class="display mt-3 text-3xl leading-[1.12] text-ink-950 sm:text-[2.4rem] rtl:leading-[1.4]">{{ __("site.services.{$key}.title") }}</h3>
                        <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __("site.services.{$key}.lead") }}</p>
                        <ul class="mt-7 grid gap-3">
                            @foreach (__("site.services.{$key}.points") as $point)
                                <li class="flex gap-3 text-[0.9375rem] text-ink-700">
                                    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-teal-900 text-brass-200"><svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a href="{{ route('site.service', $slug) }}" class="btn btn-primary group">
                                <span>{{ __('site.showcase.explore', ['service' => $service['label']]) }}</span>
                                <span class="btn-icon"><svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </a>
                            @if ($service['for_sale'])
                                <span class="inline-flex flex-wrap items-baseline gap-x-2 gap-y-0.5 rounded-full bg-white/70 px-4 py-2.5 text-sm ring-1 ring-ink-950/5">
                                    <span class="font-semibold tabular-nums text-ink-950" dir="ltr">{{ $service['price_label'] }}</span>
                                    <span class="text-ink-500">{{ __('site.services.per') }}</span>
                                </span>
                            @endif
                        </div>
                        @if ($service['for_sale'])
                            <p class="mt-3 text-sm font-medium text-teal-800">{{ __('site.services.free', ['days' => $service['demo_days']]) }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
