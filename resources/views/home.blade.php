@php
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $forSale = $services->where('for_sale', true);
@endphp
<x-site.page :title="__('site.meta.title')" :description="__('site.meta.description')" active="home">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => config('app.name'),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => __('site.meta.description'),
            'offers' => $forSale->map(fn (array $s): array => ['@type' => 'Offer', 'name' => $s['label'], 'price' => number_format($s['price'] / 10 ** $pricing['decimals'], $pricing['decimals'], '.', ''), 'priceCurrency' => $pricing['currency']])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:head>

        {{-- Hero --}}
        <section class="mx-auto grid max-w-6xl items-center gap-14 px-4 pb-20 pt-14 sm:px-6 lg:min-h-[calc(100dvh-6rem)] lg:grid-cols-12 lg:gap-8 lg:pb-28 lg:pt-10">
            <div class="lg:col-span-6">
                <p class="eyebrow reveal" style="--i:0"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.hero.eyebrow') }}</p>
                <h1 class="display reveal mt-7 text-[2.75rem] leading-[1.02] text-ink-950 sm:text-6xl lg:text-[4.5rem] rtl:text-[2.4rem] rtl:leading-[1.35] rtl:sm:text-[3.25rem] rtl:lg:text-[3.75rem]" style="--i:1">{{ __('site.hero.title') }}</h1>
                <p class="reveal mt-7 max-w-xl text-lg leading-relaxed text-ink-600" style="--i:2">{{ __('site.hero.lead') }}</p>
                <div class="reveal mt-10 flex flex-wrap items-center gap-3" style="--i:3">
                    <a href="#request" class="btn btn-primary group"><span>{{ __('site.hero.cta') }}</span><span class="btn-icon">{!! $arrow !!}</span></a>
                    <a href="#pricing" class="btn btn-ghost group"><span>{{ __('site.hero.secondary') }}</span><span class="btn-icon"><svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span></a>
                </div>
                <ul class="reveal mt-11 flex flex-wrap gap-x-6 gap-y-3 text-sm text-ink-600" style="--i:4">
                    @foreach (['free', 'bilingual', 'nothing'] as $point)
                        <li class="flex items-center gap-2.5">
                            <span class="grid size-5 place-items-center rounded-full bg-teal-900/8 text-teal-800 ring-1 ring-teal-900/10">{!! $check !!}</span>
                            {{ __("site.hero.trust.{$point}", ['days' => $demoDays]) }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- The product, in one composed moment --}}
            <div class="reveal relative mx-auto w-full max-w-md lg:col-span-6 lg:max-w-none" style="--i:2" aria-hidden="true">
                <div class="theme-fixed relative mx-auto h-[34rem] w-full max-w-[30rem] sm:h-[36rem]">
                    {{-- Phone: the guest's pager, ringing --}}
                    <div class="absolute start-1/2 top-0 w-[15.5rem] -translate-x-1/2 rtl:translate-x-1/2 sm:start-[38%] sm:w-[17rem]">
                        <div class="rounded-[2.9rem] bg-ink-950 p-2 shadow-[var(--shadow-float)] ring-1 ring-ink-950/40 outline outline-1 outline-offset-0 outline-white/10">
                            <div class="relative overflow-hidden rounded-[2.4rem] bg-ivory-50">
                                <div class="flex items-center justify-between px-5 pt-4 text-[0.625rem] font-semibold text-ink-500"><span dir="ltr">9:41</span><span class="h-4 w-16 rounded-full bg-ink-950"></span><span class="flex gap-0.5"><span class="h-2 w-3 rounded-sm bg-ink-400"></span></span></div>
                                <div class="px-4 pb-6 pt-4 text-center">
                                    <p class="text-[0.6875rem] font-semibold text-ink-500">{{ __('services.names.ready_call') }}</p>
                                    <div class="relative mx-auto mt-8 grid size-44 place-items-center">
                                        <span class="absolute size-40 rounded-full bg-status-ready/25 motion-safe:animate-[site-ring_1.6s_cubic-bezier(0.32,0.72,0,1)_infinite]"></span>
                                        <span class="absolute size-40 rounded-full bg-status-ready/20 motion-safe:animate-[site-ring_1.6s_cubic-bezier(0.32,0.72,0,1)_0.5s_infinite]"></span>
                                        <div class="relative rounded-[2rem] bg-status-ready/15 p-1.5 ring-1 ring-status-ready/30">
                                            <div class="grid min-w-32 place-items-center rounded-[1.6rem] bg-white px-7 py-5 shadow-[var(--shadow-lift)]">
                                                <span class="text-[0.5625rem] font-medium uppercase tracking-[0.2em] text-ink-400 rtl:tracking-normal">{{ __('site.stage.number') }}</span>
                                                <span class="text-6xl font-semibold leading-none tabular-nums tracking-tight text-ink-950" dir="ltr">27</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="display mt-7 text-2xl text-ink-950">{{ __('site.stage.ready') }}</p>
                                    <div class="mx-auto mt-5 h-11 w-full rounded-full bg-ink-950"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Drive-thru ticket, arriving --}}
                    <div class="absolute end-0 top-36 hidden w-56 rotate-[3deg] sm:block rounded-[1.6rem] bg-white/70 p-1.5 shadow-[var(--shadow-float)] ring-1 ring-ink-950/5 backdrop-blur-md motion-safe:animate-[site-float_7s_ease-in-out_infinite] rtl:rotate-[-3deg] sm:-end-4">
                        <div class="flex items-center gap-3 rounded-[1.25rem] bg-white p-3">
                            <img src="{{ asset('images/site/spanish-latte.webp') }}" alt="" class="size-12 rounded-xl object-cover" width="48" height="48">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink-950" dir="auto">{{ __('site.stage.order') }}</p>
                                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-500"><span class="size-1.5 rounded-full bg-status-ready"></span>{{ __('site.stage.arriving') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Stamp card, nearly full --}}
                    <div class="absolute bottom-0 start-0 w-72 rotate-[-4deg] motion-safe:animate-[site-float_8s_ease-in-out_1.5s_infinite] rtl:rotate-[4deg] sm:-start-6 sm:w-80">
                        <x-stamp-card :name="__('site.stage.stamps')" :needed="5" :filled="4" accent="#0f3b3a" class="shadow-[var(--shadow-float)]" />
                    </div>
                </div>
            </div>
        </section>

        {{-- The three services --}}
        <section id="services" class="scroll-mt-28 px-4 py-20 sm:px-6 lg:py-28">
            <div class="mx-auto max-w-6xl">
                <div class="max-w-2xl" data-reveal>
                    <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.services.eyebrow') }}</p>
                    <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('site.services.title') }}</h2>
                    <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __('site.services.lead') }}</p>
                </div>

                <div class="mt-16 space-y-24 lg:mt-20 lg:space-y-32">
                    @foreach (['drive_thru', 'ready_call', 'rewards'] as $index => $key)
                        @php $service = $services[$key]; $flip = $index % 2 === 1; @endphp
                        <article class="grid items-center gap-10 lg:grid-cols-12 lg:gap-16" aria-labelledby="service-{{ $key }}">
                            {{-- Living illustration --}}
                            <div @class(['lg:col-span-6', 'lg:order-2' => $flip]) data-reveal aria-hidden="true">
                                @include('site.partials.service-visual', ['key' => $key])
                            </div>

                            {{-- Copy --}}
                            <div @class(['lg:col-span-6', 'lg:order-1' => $flip]) data-reveal style="--i:1">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brass-700 rtl:tracking-normal">{{ __("site.services.{$key}.kicker") }}</p>
                                <h3 id="service-{{ $key }}" class="display mt-3 text-3xl leading-[1.12] text-ink-950 sm:text-[2.6rem] rtl:leading-[1.4]">{{ __("site.services.{$key}.title") }}</h3>
                                <p class="mt-5 max-w-lg text-lg leading-relaxed text-ink-600">{{ __("site.services.{$key}.lead") }}</p>
                                <ul class="mt-7 grid gap-3 sm:grid-cols-2">
                                    @foreach (__("site.services.{$key}.points") as $point)
                                        <li class="flex gap-3 text-[0.9375rem] text-ink-700">
                                            <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-teal-900 text-brass-200">{!! $check !!}</span>
                                            {{ $point }}
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($service['for_sale'])
                                    <div class="mt-8 inline-flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-2xl bg-white/70 px-5 py-3.5 ring-1 ring-ink-950/5">
                                        <span class="text-xl font-semibold tabular-nums text-ink-950" dir="ltr">{{ $service['price_label'] }}</span>
                                        <span class="text-sm text-ink-500">{{ __('site.services.per') }}</span>
                                        <span class="text-sm font-medium text-teal-800">· {{ __('site.services.free', ['days' => $service['demo_days']]) }}</span>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section id="how" class="scroll-mt-28 px-4 py-20 sm:px-6 lg:py-28">
            <div class="theme-fixed mx-auto max-w-6xl rounded-[2.5rem] bg-teal-900 px-6 py-14 text-white shadow-[var(--shadow-float)] sm:px-10 lg:px-14 lg:py-20">
                <div class="max-w-2xl" data-reveal>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brass-200 rtl:tracking-normal">{{ __('site.how.eyebrow') }}</p>
                    <h2 class="display mt-4 text-4xl leading-[1.08] sm:text-5xl rtl:leading-[1.4]">{{ __('site.how.title') }}</h2>
                </div>
                <ol class="mt-12 grid gap-4 md:grid-cols-3">
                    @foreach (__('site.how.steps') as $i => $step)
                        <li class="rounded-[1.75rem] bg-white/[0.06] p-6 ring-1 ring-white/10" data-reveal style="--i:{{ $i }}">
                            <span class="grid size-11 place-items-center rounded-full bg-brass-200 font-display text-lg font-semibold text-teal-900" dir="ltr">{{ $i + 1 }}</span>
                            <p class="mt-6 text-lg font-semibold">{{ $step['title'] }}</p>
                            <p class="mt-2 text-[0.9375rem] leading-relaxed text-white/70">{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Pricing --}}
        <section id="pricing" class="scroll-mt-28 px-4 py-20 sm:px-6 lg:py-28">
            <div class="mx-auto max-w-6xl">
                <div class="mx-auto max-w-2xl text-center" data-reveal>
                    <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.pricing.eyebrow') }}</p>
                    <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('site.pricing.title') }}</h2>
                    <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __('site.pricing.lead') }}</p>
                </div>

                <div class="mt-14">
                    @include('site.partials.pricing-calculator')
                </div>
            </div>
        </section>

        {{-- Trust --}}
        <section class="px-4 py-16 sm:px-6 lg:py-20">
            <div class="mx-auto max-w-6xl">
                <h2 class="display max-w-xl text-3xl leading-[1.12] text-ink-950 sm:text-4xl rtl:leading-[1.4]" data-reveal>{{ __('site.trust.title') }}</h2>
                <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (__('site.trust.items') as $i => $item)
                        <li class="rounded-[1.75rem] bg-white/70 p-6 ring-1 ring-ink-950/5" data-reveal style="--i:{{ $i }}">
                            <span class="block h-px w-10 bg-brass-500"></span>
                            <p class="mt-5 font-semibold text-ink-950">{{ $item['title'] }}</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $item['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>


        {{-- FAQ --}}
        <section id="faq" class="scroll-mt-28 px-4 py-16 sm:px-6 lg:py-24">
            <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-12">
                <div class="lg:col-span-4" data-reveal>
                    <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.faq.eyebrow') }}</p>
                    <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 rtl:leading-[1.4]">{{ __('site.faq.title') }}</h2>
                </div>
                <div class="lg:col-span-8">
                    @include('site.partials.faq-list', ['items' => __('site.faq.items')])
                </div>
            </div>
        </section>


        {{-- Closing line --}}
        <section class="px-4 pb-10 pt-8 sm:px-6">
            <div class="mx-auto max-w-4xl text-center" data-reveal>
                <h2 class="display text-3xl leading-[1.15] text-ink-950 sm:text-5xl rtl:leading-[1.45]">{{ __('site.closing.title') }}</h2>
                <p class="mt-5 text-lg text-ink-600">{{ __('site.closing.lead') }}</p>
            </div>
        </section>

        @include('partials.request-access', ['services' => $forSale])
</x-site.page>
