@php
    use App\Support\Locales;
    $next = Locales::other();
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $forSale = $services->where('for_sale', true);
@endphp
<x-layouts.site :title="__('site.meta.title')" :description="__('site.meta.description')" body-class="grain overflow-x-hidden bg-ivory-50">
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

    {{-- Ambient light, fixed so scrolling never repaints it --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-0">
        <div class="absolute -top-48 end-[-12rem] size-[46rem] rounded-full bg-teal-900/14 blur-3xl"></div>
        <div class="absolute top-[40%] start-[-14rem] size-[40rem] rounded-full bg-brass-400/18 blur-3xl"></div>
    </div>

    {{-- Floating island navigation --}}
    <header class="sticky top-0 z-30 px-3 pt-3 sm:px-4 sm:pt-5">
        <nav class="mx-auto flex max-w-6xl items-center justify-between gap-2 rounded-full bg-white/70 py-1.5 pe-1.5 ps-3 shadow-[var(--shadow-soft)] ring-1 ring-ink-950/5 backdrop-blur-xl sm:gap-3 sm:py-2 sm:pe-2" aria-label="{{ __('site.nav.label') }}">
            <a href="{{ route('home') }}" class="shrink-0 rounded-full text-ink-950" aria-label="{{ config('app.name') }}"><x-brand.logo /></a>
            <ul class="hidden items-center gap-1 text-sm font-medium text-ink-600 md:flex">
                @foreach (['services', 'how', 'pricing', 'faq'] as $anchor)
                    <li><a href="#{{ $anchor }}" class="rounded-full px-3.5 py-2 transition hover:bg-white hover:text-ink-950">{{ __("site.nav.{$anchor}") }}</a></li>
                @endforeach
            </ul>
            <div class="flex items-center gap-1 sm:gap-1.5">
                <a href="{{ route('locale.switch', $next) }}" lang="{{ $next }}" hreflang="{{ $next }}" class="rounded-full px-3 py-2 text-sm font-medium text-ink-700 transition hover:bg-white hover:text-ink-950">{{ Locales::native($next) }}</a>
                <a href="{{ url('/app') }}" class="hidden rounded-full px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-white hover:text-ink-950 sm:inline-flex">{{ __('site.nav.sign_in') }}</a>
                <a href="#request" class="inline-flex min-h-10 items-center rounded-full bg-teal-900 px-4 text-sm font-semibold text-white shadow-[var(--shadow-lift)] transition hover:bg-teal-800 active:scale-[0.98]">{{ __('site.nav.demo') }}</a>
            </div>
        </nav>
    </header>

    <main id="main" class="relative z-10">
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
                <div class="relative mx-auto h-[34rem] w-full max-w-[30rem] sm:h-[36rem]">
                    {{-- Phone: the guest's pager, ringing --}}
                    <div class="absolute start-1/2 top-0 w-[15.5rem] -translate-x-1/2 rtl:translate-x-1/2 sm:start-[38%] sm:w-[17rem]">
                        <div class="rounded-[2.9rem] bg-ink-950 p-2 shadow-[var(--shadow-float)] ring-1 ring-ink-950/40">
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
                                <div class="bezel">
                                    <div class="bezel-core relative h-[22rem] overflow-hidden sm:h-[26rem]">
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
            <div class="mx-auto max-w-6xl rounded-[2.5rem] bg-teal-900 px-6 py-14 text-white shadow-[var(--shadow-float)] sm:px-10 lg:px-14 lg:py-20">
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

        {{-- Pricing: the real price list, worked out live --}}
        <section id="pricing" class="scroll-mt-28 px-4 py-20 sm:px-6 lg:py-28"
                 x-data="{
                    prices: {{ \Illuminate\Support\Js::from($forSale->mapWithKeys(fn (array $s): array => [$s['key'] => $s['price']])) }},
                    picked: {{ \Illuminate\Support\Js::from($forSale->keys()->values()) }},
                    branches: 1,
                    two: {{ $pricing['two'] }}, three: {{ $pricing['three'] }},
                    toggle(key) { this.picked = this.picked.includes(key) ? this.picked.filter((k) => k !== key) : [...this.picked, key] },
                    get subtotal() { return this.picked.reduce((sum, key) => sum + (this.prices[key] ?? 0) * this.branches, 0) },
                    get rate() { return this.picked.length >= 3 ? this.three : (this.picked.length === 2 ? this.two : 0) },
                    get discount() { return Math.round(this.subtotal * this.rate / 10000) },
                    get total() { return this.subtotal - this.discount },
                    money(minor) { return (minor / 10 ** {{ $pricing['decimals'] }}).toLocaleString('en-US', { minimumFractionDigits: {{ $pricing['decimals'] }}, maximumFractionDigits: {{ $pricing['decimals'] }} }) + ' ' + {{ \Illuminate\Support\Js::from($pricing['symbol']) }} },
                    book() { window.dispatchEvent(new CustomEvent('pick-services', { detail: { services: this.picked, branches: this.branches } })); document.getElementById('request')?.scrollIntoView({ behavior: 'smooth' }) },
                 }">
            <div class="mx-auto max-w-6xl">
                <div class="mx-auto max-w-2xl text-center" data-reveal>
                    <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.pricing.eyebrow') }}</p>
                    <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('site.pricing.title') }}</h2>
                    <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __('site.pricing.lead') }}</p>
                </div>

                <div class="mt-14 grid gap-5 lg:grid-cols-12">
                    <ul class="grid gap-3 lg:col-span-7" data-reveal>
                        @foreach ($forSale as $key => $service)
                            <li>
                                <button type="button" x-on:click="toggle('{{ $key }}')" :aria-pressed="picked.includes('{{ $key }}')"
                                        :class="picked.includes('{{ $key }}') ? 'bg-white ring-2 ring-teal-800 shadow-[var(--shadow-lift)]' : 'bg-white/60 ring-1 ring-ink-950/8 hover:bg-white'"
                                        class="flex w-full items-center gap-4 rounded-[1.6rem] p-4 text-start transition duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] active:scale-[0.99] sm:p-5">
                                    <span :class="picked.includes('{{ $key }}') ? 'bg-teal-900 text-brass-200' : 'bg-ink-100 text-ink-500'" class="grid size-12 shrink-0 place-items-center rounded-2xl transition">
                                        <x-filament::icon :icon="\App\Domain\Services\Enums\Service::from($key)->getIcon()" class="size-6" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-ink-950">{{ $service['label'] }}</span>
                                        <span class="mt-0.5 block text-sm text-ink-500">{{ __('services.taglines.'.$key) }}</span>
                                    </span>
                                    <span class="text-end">
                                        <span class="block font-semibold tabular-nums text-ink-950" dir="ltr">{{ $service['price_label'] }}</span>
                                        <span class="block text-xs text-ink-500">{{ __('site.services.per') }}</span>
                                    </span>
                                    <span :class="picked.includes('{{ $key }}') ? 'bg-teal-900 text-white ring-teal-900' : 'bg-white text-transparent ring-ink-950/15'" class="grid size-6 shrink-0 place-items-center rounded-full ring-1 transition">{!! $check !!}</span>
                                </button>
                            </li>
                        @endforeach
                        <li class="flex items-center justify-between gap-4 rounded-[1.6rem] bg-white/60 p-4 ring-1 ring-ink-950/8 sm:p-5">
                            <span class="font-semibold text-ink-950">{{ __('site.pricing.branches') }}</span>
                            <span class="flex items-center rounded-full bg-ivory-100 p-1 ring-1 ring-ink-950/5">
                                <button type="button" x-on:click="branches = Math.max(1, branches - 1)" :disabled="branches <= 1" class="grid size-10 place-items-center rounded-full text-xl text-ink-800 transition enabled:hover:bg-white disabled:opacity-30" aria-label="{{ __('site.pricing.less') }}">−</button>
                                <span class="w-10 text-center text-lg font-semibold tabular-nums text-ink-950" x-text="branches" dir="ltr" aria-live="polite">1</span>
                                <button type="button" x-on:click="branches = Math.min(50, branches + 1)" class="grid size-10 place-items-center rounded-full text-xl text-ink-800 transition hover:bg-white" aria-label="{{ __('site.pricing.more') }}">+</button>
                            </span>
                        </li>
                    </ul>

                    <div class="lg:col-span-5" data-reveal style="--i:1">
                        <div class="sticky top-28 rounded-[2rem] bg-ink-950 p-1.5 shadow-[var(--shadow-float)]">
                            <div class="rounded-[calc(2rem-0.375rem)] bg-[radial-gradient(120%_80%_at_100%_0%,color-mix(in_oklab,var(--color-teal-800)_55%,transparent),transparent)] p-6 text-white sm:p-8">
                                <template x-if="picked.length === 0">
                                    <p class="py-10 text-center text-white/60">{{ __('site.pricing.pick') }}</p>
                                </template>
                                <div x-show="picked.length > 0">
                                    <dl class="space-y-3 text-sm">
                                        <div class="flex justify-between text-white/70"><dt>{{ __('site.pricing.subtotal') }}</dt><dd class="tabular-nums" dir="ltr" x-text="money(subtotal)"></dd></div>
                                        <div class="flex justify-between text-brass-200" x-show="discount > 0" x-cloak>
                                            <dt x-text="{{ \Illuminate\Support\Js::from(__('site.pricing.bundle', ['percent' => '__P__'])) }}.replace('__P__', (rate / 100).toString())"></dt>
                                            <dd class="tabular-nums" dir="ltr" x-text="'−' + money(discount)"></dd>
                                        </div>
                                    </dl>
                                    <div class="mt-6 border-t border-white/10 pt-6">
                                        <p class="text-sm text-white/60">{{ __('site.pricing.total') }}</p>
                                        <p class="mt-1 text-5xl font-semibold tabular-nums tracking-tight" dir="ltr" x-text="money(total)"></p>
                                    </div>
                                </div>
                                <p class="mt-6 text-sm text-white/60">{{ __('site.pricing.bundle_hint', ['two' => rtrim(rtrim(number_format($pricing['two'] / 100, 2), '0'), '.'), 'three' => rtrim(rtrim(number_format($pricing['three'] / 100, 2), '0'), '.')]) }}</p>
                                <button type="button" x-on:click="book()" :disabled="picked.length === 0"
                                        class="mt-7 flex min-h-14 w-full items-center justify-between gap-3 rounded-full bg-brass-200 py-2 pe-2 ps-6 font-semibold text-teal-900 transition hover:bg-brass-100 active:scale-[0.99] disabled:opacity-50">
                                    <span>{{ __('site.pricing.cta') }}</span>
                                    <span class="grid size-10 place-items-center rounded-full bg-teal-900 text-brass-200">{!! $arrow !!}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mx-auto mt-6 max-w-2xl text-center text-sm text-ink-500" data-reveal>{{ __('site.pricing.note', ['currency' => $pricing['currency'], 'days' => $demoDays]) }}</p>
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
                <div class="space-y-3 lg:col-span-8">
                    @foreach (__('site.faq.items') as $i => $item)
                        <details class="group rounded-[1.5rem] bg-white/70 ring-1 ring-ink-950/5 transition open:bg-white open:shadow-[var(--shadow-soft)] [&_summary::-webkit-details-marker]:hidden" data-reveal style="--i:{{ $i }}">
                            <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-6 text-start font-semibold text-ink-950">
                                {{ $item['q'] }}
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-ivory-100 text-ink-700 transition duration-300 group-open:rotate-45"><svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3.5v9M3.5 8h9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            </summary>
                            <p class="px-6 pb-6 leading-relaxed text-ink-600">{{ str_replace(':days', (string) $demoDays, $item['a']) }}</p>
                        </details>
                    @endforeach
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
    </main>

    <footer class="relative z-10 px-4 pb-10 sm:px-6">
        <div class="mx-auto max-w-6xl border-t border-ink-950/8 pt-10">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-brand.logo />
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-500">{{ __('app.tagline') }}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-ink-950">{{ __('site.footer.product') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-ink-600">
                        @foreach (['drive_thru', 'ready_call', 'rewards'] as $key)
                            <li><a href="#services" class="transition hover:text-ink-950">{{ __('services.names.'.$key) }}</a></li>
                        @endforeach
                        <li><a href="#pricing" class="transition hover:text-ink-950">{{ __('site.nav.pricing') }}</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold text-ink-950">{{ __('site.footer.company') }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-ink-600">
                        <li><a href="{{ url('/app') }}" class="transition hover:text-ink-950">{{ __('site.footer.restaurant_login') }}</a></li>
                        <li><a href="{{ url('/admin') }}" class="transition hover:text-ink-950">{{ __('site.footer.platform') }}</a></li>
                        <li><a href="{{ route('api.docs') }}" class="transition hover:text-ink-950">{{ __('site.footer.api') }}</a></li>
                    </ul>
                </div>
            </div>
            <p class="mt-12 text-sm text-ink-500">© <span dir="ltr">{{ now()->year }}</span> {{ config('app.name') }}. {{ __('app.home.rights') }}</p>
        </div>
    </footer>

    <style>
        @keyframes site-ring { from { transform: scale(0.8); opacity: 0.9; } to { transform: scale(1.45); opacity: 0; } }
        @keyframes site-float { 0%, 100% { translate: 0 0; } 50% { translate: 0 -10px; } }
        @keyframes site-glow { 0%, 100% { box-shadow: 0 0 0 0 color-mix(in oklab, var(--color-status-ready) 0%, transparent); } 50% { box-shadow: 0 0 0 8px color-mix(in oklab, var(--color-status-ready) 25%, transparent); } }
    </style>
</x-layouts.site>
