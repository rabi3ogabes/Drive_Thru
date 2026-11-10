@php
    $forSale = $services->where('for_sale', true);
@endphp
<x-site.page :title="__('site.meta.title')" :description="__('site.meta.description')" active="home">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => \App\Support\Brand::name(),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => __('site.meta.description'),
            'offers' => $forSale->map(fn (array $s): array => ['@type' => 'Offer', 'name' => $s['label'], 'price' => number_format($s['price'] / 10 ** $pricing['decimals'], $pricing['decimals'], '.', ''), 'priceCurrency' => $pricing['currency']])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:head>

        {{-- Hero: the three services, one slide each --}}
        @include('site.partials.hero')

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

        @include('partials.request-access', ['services' => $forSale, 'preselect' => $preselect ?? []])
</x-site.page>
