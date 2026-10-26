@php
    use App\Domain\Services\Enums\Service;
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $forSale = $services->where('for_sale', true);
    $count = fn (?int $n): string => $n === null ? __('site.pricing_page.unlimited') : (string) $n;
@endphp
<x-site.page :title="__('site.pricing_page.meta')" :description="__('site.pricing_page.lead')" active="pricing">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'OfferCatalog',
            'name' => __('site.pricing_page.meta'),
            'itemListElement' => $forSale->map(fn (array $s): array => ['@type' => 'Offer', 'name' => $s['label'], 'price' => number_format($s['price'] / 10 ** $pricing['decimals'], $pricing['decimals'], '.', ''), 'priceCurrency' => $pricing['currency']])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:head>

    {{-- Header and the calculator --}}
    <section class="px-4 pb-16 pt-12 sm:px-6 lg:pb-24 lg:pt-16">
        <div class="mx-auto max-w-6xl">
            <div class="mx-auto max-w-2xl text-center">
                <p class="eyebrow reveal" style="--i:0"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.pricing.eyebrow') }}</p>
                <h1 class="display reveal mt-7 text-[2.6rem] leading-[1.05] text-ink-950 sm:text-6xl rtl:text-[2.3rem] rtl:leading-[1.35] rtl:sm:text-[3rem]" style="--i:1">{{ __('site.pricing_page.title') }}</h1>
                <p class="reveal mt-6 text-lg leading-relaxed text-ink-600" style="--i:2">{{ __('site.pricing_page.lead') }}</p>
            </div>
            <div class="reveal mt-14" style="--i:3">
                @include('site.partials.pricing-calculator')
            </div>
        </div>
    </section>

    {{-- What each service includes --}}
    <section class="px-4 py-16 sm:px-6 lg:py-24" aria-labelledby="compare-title">
        <div class="mx-auto max-w-6xl">
            <h2 id="compare-title" class="display max-w-2xl text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]" data-reveal>{{ __('site.pricing_page.compare_title') }}</h2>
            <ul class="mt-12 grid gap-4 lg:grid-cols-3">
                @foreach (Service::cases() as $i => $service)
                    @php $info = $services[$service->value]; @endphp
                    <li class="flex" data-reveal style="--i:{{ $i }}">
                        <article class="flex w-full flex-col rounded-[2rem] bg-white/70 p-1.5 ring-1 ring-ink-950/5" aria-labelledby="plan-{{ $service->value }}">
                            <div class="flex flex-1 flex-col rounded-[calc(2rem-0.375rem)] bg-white p-6 shadow-[var(--shadow-soft)]">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-11 place-items-center rounded-2xl bg-teal-900 text-brass-200"><x-filament::icon :icon="$service->getIcon()" class="size-5" /></span>
                                    <h3 id="plan-{{ $service->value }}" class="text-lg font-semibold text-ink-950">{{ $service->getLabel() }}</h3>
                                </div>
                                <p class="mt-3 text-sm text-ink-500">{{ __('services.taglines.'.$service->value) }}</p>
                                @if ($info['for_sale'])
                                    <p class="mt-6 flex items-baseline gap-2">
                                        <span class="text-3xl font-semibold tabular-nums tracking-tight text-ink-950" dir="ltr">{{ $info['price_label'] }}</span>
                                    </p>
                                    <p class="mt-1 text-sm text-ink-500">{{ __('site.services.per') }} · <span class="font-medium text-teal-800">{{ __('site.services.free', ['days' => $info['demo_days']]) }}</span></p>
                                @endif
                                <ul class="mt-6 space-y-3 border-t border-ink-950/6 pt-6">
                                    @foreach (__('site.service_page.'.$service->value.'.features') as $feature)
                                        <li class="flex gap-3 text-[0.9375rem] text-ink-700">
                                            <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-teal-900 text-brass-200">{!! $check !!}</span>
                                            {{ $feature['title'] }}
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('site.service', $service->slug()) }}" class="mt-auto inline-flex items-center gap-2 pt-7 text-sm font-semibold text-teal-800 transition hover:gap-3">
                                    {{ __('site.service_page.learn') }} {!! $arrow !!}
                                </a>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Plan tiers: limits and extras, straight from the plans --}}
    @if ($tiers->isNotEmpty())
        <section class="px-4 py-16 sm:px-6 lg:py-24" aria-labelledby="tiers-title">
            <div class="mx-auto max-w-6xl">
                <div class="max-w-2xl" data-reveal>
                    <h2 id="tiers-title" class="display text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('site.pricing_page.tiers_title') }}</h2>
                    <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __('site.pricing_page.tiers_lead') }}</p>
                </div>
                <ul class="mt-12 grid gap-4 md:grid-cols-3">
                    @foreach ($tiers as $i => $tier)
                        <li data-reveal style="--i:{{ $i }}" @class([
                            'flex flex-col rounded-[2rem] p-6 ring-1',
                            'bg-white shadow-[var(--shadow-lift)] ring-2 ring-teal-800' => $tier['highlighted'],
                            'bg-white/70 ring-ink-950/5' => ! $tier['highlighted'],
                        ])>
                            <h3 class="font-display text-2xl text-ink-950">{{ $tier['name'] }}</h3>
                            @if ($tier['description'])<p class="mt-2 text-sm leading-relaxed text-ink-500">{{ $tier['description'] }}</p>@endif
                            <dl class="mt-6 space-y-3 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-ink-500">{{ __('site.pricing_page.tier_fee') }}</dt>
                                    <dd class="font-semibold text-ink-950" @if ($tier['fee']) dir="ltr" @endif>{{ $tier['fee'] === null ? __('site.pricing_page.tier_custom') : ($tier['fee'] === 0 ? __('site.pricing_page.tier_free') : $tier['fee_label']) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-ink-500">{{ __('site.pricing_page.branches') }}</dt><dd class="font-semibold tabular-nums text-ink-950">{{ $count($tier['branches']) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-ink-500">{{ __('site.pricing_page.employees') }}</dt><dd class="font-semibold tabular-nums text-ink-950">{{ $count($tier['employees']) }}</dd></div>
                            </dl>
                            <div class="mt-6 border-t border-ink-950/6 pt-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-ink-400 rtl:tracking-normal">{{ __('site.pricing_page.addons') }}</p>
                                @if ($tier['addons'] === [])
                                    <p class="mt-3 text-sm text-ink-600">{{ __('site.pricing_page.no_addons') }}</p>
                                @else
                                    <ul class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($tier['addons'] as $addon)
                                            <li class="rounded-full bg-ivory-100 px-3 py-1 text-xs font-medium text-ink-700 ring-1 ring-ink-950/5">{{ $addon }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- How billing works --}}
    <section id="faq" class="scroll-mt-28 px-4 py-16 sm:px-6 lg:py-24">
        <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4" data-reveal>
                <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.faq.eyebrow') }}</p>
                <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 rtl:leading-[1.4]">{{ __('site.pricing_page.billing_title') }}</h2>
            </div>
            <div class="lg:col-span-8">
                @include('site.partials.faq-list', ['items' => __('site.pricing_page.billing')])
            </div>
        </div>
    </section>

    @include('partials.request-access', ['services' => $forSale])
</x-site.page>
