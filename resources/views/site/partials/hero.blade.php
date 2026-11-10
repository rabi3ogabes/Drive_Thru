@php
    $slides = ['drive_thru' => 'drive-thru', 'ready_call' => 'ready-call', 'rewards' => 'rewards'];
    $count = count($slides);
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
@endphp
{{--
    The hero: one slide per service, each a headline beside its scene in motion (resources/js/showcase.js).
    Slide 1 is rendered visible and its headline is the page's h1; without JavaScript the tabs are links that show their slide (:target).
    Hidden slides are made inert by Alpine, not on the server, so a slide shown with :target stays usable.
--}}
<section id="hero" class="showcase relative isolate"
         x-data="serviceShowcase({{ $count }})" :class="{ 'is-paused': paused, 'has-moved': moved }"
         aria-roledescription="carousel" aria-label="{{ __('site.hero.label') }}">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(60%_60%_at_80%_10%,color-mix(in_oklab,var(--color-brass-200)_45%,transparent),transparent_70%)]"></div>

    <div class="mx-auto max-w-6xl px-4 pb-16 pt-10 sm:px-6 lg:flex lg:min-h-[calc(100dvh-6rem)] lg:flex-col lg:justify-center lg:pb-20 lg:pt-4">
        <p class="eyebrow reveal w-max self-start" style="--i:0"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.hero.eyebrow') }}</p>

        {{-- Slides --}}
        <div class="sc-slides mt-8 lg:mt-10" x-ref="stage" :aria-live="paused ? 'polite' : 'off'"
             @mouseenter="hovered = true" @mouseleave="hovered = false"
             @focusin="focused = true" @focusout="focused = $el.contains($event.relatedTarget)"
             @pointerdown="swipeStart($event)" @pointerup="swipeEnd($event)" @pointercancel="touch = null"
             style="touch-action: pan-y">
            @foreach ($slides as $key => $slug)
                @php
                    $service = $services[$key];
                    $index = $loop->index;
                    $points = array_slice((array) __("site.services.{$key}.points"), 0, 3);
                @endphp
                <article id="hero-{{ $key }}" role="tabpanel" aria-roledescription="slide" aria-labelledby="hero-title-{{ $key }}"
                         @class(['sc-slide grid items-center gap-8 lg:grid-cols-12 lg:gap-12', 'is-active' => $index === 0])
                         :class="{ 'is-active': active === {{ $index }} }" :inert="active !== {{ $index }}">
                    {{-- The words --}}
                    <div class="order-2 lg:order-1 lg:col-span-5">
                        <p class="hero-line flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-brass-700 rtl:tracking-normal" style="--i:0">
                            <x-filament::icon :icon="\App\Domain\Services\Enums\Service::from($key)->getIcon()" class="size-4" />
                            {{ __("site.services.{$key}.kicker") }}
                        </p>
                        <{{ $index === 0 ? 'h1' : 'h2' }} id="hero-title-{{ $key }}" class="hero-line display mt-4 text-[2.6rem] leading-[1.04] text-ink-950 sm:text-6xl lg:text-[4.1rem] rtl:text-[2.3rem] rtl:leading-[1.35] rtl:sm:text-5xl rtl:lg:text-[3.4rem]" style="--i:1">
                            {{ __("site.hero.slides.{$key}.title") }}
                        </{{ $index === 0 ? 'h1' : 'h2' }}>
                        <p class="hero-line mt-6 max-w-xl text-lg leading-relaxed text-ink-600" style="--i:2">{{ __("site.hero.slides.{$key}.lead") }}</p>
                        <ul class="hero-line mt-7 grid gap-2.5" style="--i:3">
                            @foreach ($points as $point)
                                <li class="flex gap-3 text-[0.9375rem] text-ink-700">
                                    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-teal-900 text-brass-200">{!! $check !!}</span>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                        <div class="hero-line mt-9 flex flex-wrap items-center gap-3" style="--i:4">
                            @if ($service['for_sale'])
                                <a href="{{ route('home', ['service' => $key]) }}#request" class="btn btn-primary group" @click.prevent="pick(@js($key))">
                                    <span>{{ __('site.hero.start', ['days' => $service['demo_days']]) }}</span><span class="btn-icon">{!! $arrow !!}</span>
                                </a>
                            @else
                                <a href="#request" class="btn btn-primary group"><span>{{ __('site.hero.contact') }}</span><span class="btn-icon">{!! $arrow !!}</span></a>
                            @endif
                            <a href="{{ route('site.service', $slug) }}" class="btn btn-ghost group"><span>{{ __('site.hero.learn') }}</span></a>
                        </div>
                    </div>

                    {{-- The scene --}}
                    <div class="sc-scene order-1 lg:order-2 lg:col-span-7" x-ref="scene{{ $index }}" aria-hidden="true">
                        @include('site.partials.scenes.'.$slug)
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Tabs (with the line that fills while a slide plays), pause, and the promise --}}
        <div class="mt-10 flex flex-col gap-6 lg:mt-12">
            <div class="flex items-end gap-4 sm:gap-8">
                <div class="grid flex-1 grid-cols-3 gap-2 sm:gap-6" role="tablist" aria-label="{{ __('site.hero.label') }}" @keydown="key($event)">
                    @foreach (array_keys($slides) as $index => $key)
                        <a href="#hero-{{ $key }}" role="tab" id="hero-tab-{{ $key }}" aria-controls="hero-{{ $key }}" x-ref="tab{{ $index }}"
                           @click.prevent="show({{ $index }}, { user: true })"
                           :aria-selected="active === {{ $index }} ? 'true' : 'false'" :tabindex="active === {{ $index }} ? 0 : -1"
                           class="group rounded-2xl px-1 pb-3 pt-1 text-start outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-4 focus-visible:ring-offset-ivory-50"
                           aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            <span class="flex items-baseline gap-2.5">
                                <span class="hidden text-xs font-semibold tabular-nums transition-colors duration-500 sm:inline" :class="active === {{ $index }} ? 'text-brass-700' : 'text-ink-400'" dir="ltr">0{{ $index + 1 }}</span>
                                <span class="text-[0.8125rem] font-semibold leading-tight transition-colors duration-500 sm:text-base" :class="active === {{ $index }} ? 'text-ink-950' : 'text-ink-500 group-hover:text-ink-800'">{{ __("site.services.{$key}.kicker") }}</span>
                            </span>
                            <span class="sc-progress mt-3 block" x-ref="bar{{ $index }}" @animationend="ended($event, {{ $index }})"
                                  :class="{ 'is-done': still ? active === {{ $index }} : active > {{ $index }} }"></span>
                        </a>
                    @endforeach
                </div>
                <button type="button" @click="toggle()" x-show="!still" x-cloak
                        class="mb-2 grid size-11 shrink-0 place-items-center rounded-full bg-white/70 text-ink-950 ring-1 ring-ink-950/10 backdrop-blur transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-white active:scale-95"
                        :aria-label="stopped ? @js(__('site.showcase.play')) : @js(__('site.showcase.pause'))" :aria-pressed="stopped ? 'true' : 'false'">
                    <svg x-show="!stopped" class="size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><rect x="4" y="3" width="2.6" height="10" rx="1"/><rect x="9.4" y="3" width="2.6" height="10" rx="1"/></svg>
                    <svg x-show="stopped" x-cloak class="flip-rtl size-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 3.4v9.2a.8.8 0 0 0 1.2.7l7.2-4.6a.8.8 0 0 0 0-1.4L6.2 2.7A.8.8 0 0 0 5 3.4Z"/></svg>
                </button>
            </div>

            <ul class="flex flex-wrap gap-x-6 gap-y-3 text-sm text-ink-600">
                @foreach (['free', 'bilingual', 'nothing'] as $promise)
                    <li class="flex items-center gap-2.5">
                        <span class="grid size-5 place-items-center rounded-full bg-teal-900/8 text-teal-800 ring-1 ring-teal-900/10">{!! $check !!}</span>
                        {{ __("site.hero.trust.{$promise}", ['days' => $demoDays]) }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
