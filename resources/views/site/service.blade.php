@php
    use App\Domain\Services\Enums\Service;
    $check = '<svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.2 2.2 4.8-4.9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $arrow = '<svg class="flip-rtl size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $key = $service->value;
    $info = $services[$key];
    $forSale = $services->where('for_sale', true);
    $others = array_values(array_filter(Service::cases(), fn (Service $s): bool => $s !== $service));
    $percent = fn (int $bp): string => rtrim(rtrim(number_format($bp / 100, 2), '0'), '.');
    $faq = __("site.service_page.{$key}.faq");
@endphp
<x-site.page :title="__('site.service_page.meta', ['service' => $service->getLabel(), 'tagline' => __('services.taglines.'.$key)])" :description="__('site.services.'.$key.'.lead')" active="service">
    <x-slot:head>
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Service',
                    'name' => $service->getLabel(),
                    'description' => __('site.services.'.$key.'.lead'),
                    'provider' => ['@type' => 'Organization', 'name' => config('app.name')],
                    'offers' => $info['for_sale'] ? ['@type' => 'Offer', 'price' => number_format($info['price'] / 10 ** $pricing['decimals'], $pricing['decimals'], '.', ''), 'priceCurrency' => $pricing['currency']] : null,
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => __('site.breadcrumb.home'), 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $service->getLabel(), 'item' => url()->current()],
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(fn (array $item): array => ['@type' => 'Question', 'name' => $item['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']]], $faq),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="mx-auto grid max-w-6xl items-center gap-12 px-4 pb-16 pt-10 sm:px-6 lg:grid-cols-12 lg:gap-14 lg:pb-24 lg:pt-14">
        <div class="lg:col-span-6">
            <nav aria-label="{{ __('site.breadcrumb.services') }}" class="reveal" style="--i:0">
                <ol class="flex items-center gap-2 text-sm text-ink-500">
                    <li><a href="{{ route('home') }}" class="transition hover:text-ink-950">{{ __('site.breadcrumb.home') }}</a></li>
                    <li aria-hidden="true"><svg class="flip-rtl size-3.5" viewBox="0 0 16 16" fill="none"><path d="m6 4 4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></li>
                    <li><a href="{{ route('home') }}#services" class="transition hover:text-ink-950">{{ __('site.breadcrumb.services') }}</a></li>
                    <li aria-hidden="true"><svg class="flip-rtl size-3.5" viewBox="0 0 16 16" fill="none"><path d="m6 4 4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></li>
                    <li aria-current="page" class="font-medium text-ink-800">{{ $service->getLabel() }}</li>
                </ol>
            </nav>
            <p class="eyebrow reveal mt-8" style="--i:1">
                <x-filament::icon :icon="$service->getIcon()" class="size-3.5 text-brass-700" />
                {{ __("site.services.{$key}.kicker") }}
            </p>
            <h1 class="display reveal mt-6 text-[2.6rem] leading-[1.04] text-ink-950 sm:text-6xl lg:text-[4rem] rtl:text-[2.3rem] rtl:leading-[1.35] rtl:sm:text-[3rem] rtl:lg:text-[3.5rem]" style="--i:2">{{ __("site.services.{$key}.title") }}</h1>
            <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-ink-600" style="--i:3">{{ __("site.services.{$key}.lead") }}</p>
            <div class="reveal mt-9 flex flex-wrap items-center gap-3" style="--i:4">
                <a href="#request" class="btn btn-primary group"><span>{{ __('site.service_page.cta', ['service' => $service->getLabel()]) }}</span><span class="btn-icon">{!! $arrow !!}</span></a>
                <a href="{{ route('site.pricing') }}" class="btn btn-ghost group"><span>{{ __('site.service_page.all_pricing') }}</span><span class="btn-icon">{!! $arrow !!}</span></a>
            </div>
            @if ($info['for_sale'])
                <p class="reveal mt-8 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm text-ink-500" style="--i:5">
                    <span class="text-xl font-semibold tabular-nums text-ink-950" dir="ltr">{{ $info['price_label'] }}</span>
                    {{ __('site.services.per') }}
                    <span class="font-medium text-teal-800">· {{ __('site.services.free', ['days' => $info['demo_days']]) }}</span>
                </p>
            @endif
        </div>
        <div class="reveal lg:col-span-6" style="--i:2" aria-hidden="true">
            @include('site.partials.service-visual', ['key' => $key])
        </div>
    </section>

    {{-- Everything it does --}}
    <section class="px-4 py-16 sm:px-6 lg:py-24" aria-labelledby="features-title">
        <div class="mx-auto max-w-6xl">
            <h2 id="features-title" class="display max-w-2xl text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]" data-reveal>{{ __('site.service_page.features_title') }}</h2>
            <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__("site.service_page.{$key}.features") as $i => $feature)
                    <li class="group rounded-[1.75rem] bg-white/70 p-1.5 ring-1 ring-ink-950/5 transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5 hover:bg-white hover:shadow-[var(--shadow-lift)]" data-reveal style="--i:{{ $i % 3 }}">
                        <div class="h-full rounded-[calc(1.75rem-0.375rem)] p-5">
                            <span class="grid size-10 place-items-center rounded-2xl bg-teal-900 font-display text-sm font-semibold text-brass-200" dir="ltr">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <p class="mt-6 text-lg font-semibold text-ink-950">{{ $feature['title'] }}</p>
                            <p class="mt-2 text-[0.9375rem] leading-relaxed text-ink-600">{{ $feature['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- How guests use it --}}
    <section id="how" class="scroll-mt-28 px-4 py-16 sm:px-6 lg:py-24" aria-labelledby="flow-title">
        <div class="theme-fixed mx-auto max-w-6xl rounded-[2.5rem] bg-teal-900 px-6 py-14 text-white shadow-[var(--shadow-float)] sm:px-10 lg:px-14 lg:py-20">
            <div class="max-w-2xl" data-reveal>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brass-200 rtl:tracking-normal">{{ $service->getLabel() }}</p>
                <h2 id="flow-title" class="display mt-4 text-4xl leading-[1.08] sm:text-5xl rtl:leading-[1.4]">{{ __('site.service_page.flow_title') }}</h2>
            </div>
            <ol class="mt-12 grid gap-4 md:grid-cols-3">
                @foreach (__("site.service_page.{$key}.flow") as $i => $step)
                    <li class="relative rounded-[1.75rem] bg-white/[0.06] p-6 ring-1 ring-white/10" data-reveal style="--i:{{ $i }}">
                        <span class="grid size-11 place-items-center rounded-full bg-brass-200 font-display text-lg font-semibold text-teal-900" dir="ltr">{{ $i + 1 }}</span>
                        <p class="mt-6 text-lg font-semibold">{{ $step['title'] }}</p>
                        <p class="mt-2 text-[0.9375rem] leading-relaxed text-white/70">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Better together --}}
    <section class="px-4 py-16 sm:px-6 lg:py-24" aria-labelledby="together-title">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-2xl" data-reveal>
                <h2 id="together-title" class="display text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('site.service_page.together_title') }}</h2>
                <p class="mt-5 text-lg leading-relaxed text-ink-600">{{ __('site.service_page.together_lead', ['two' => $percent($pricing['two']), 'three' => $percent($pricing['three'])]) }}</p>
            </div>
            <ul class="mt-10 grid gap-4 md:grid-cols-2">
                @foreach ($others as $i => $other)
                    <li data-reveal style="--i:{{ $i }}">
                        <a href="{{ route('site.service', $other->slug()) }}" class="group flex h-full items-center gap-5 rounded-[1.75rem] bg-white/70 p-5 ring-1 ring-ink-950/5 transition duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-white hover:shadow-[var(--shadow-lift)] sm:p-6">
                            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-teal-900 text-brass-200"><x-filament::icon :icon="$other->getIcon()" class="size-6" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-semibold text-ink-950">{{ $other->getLabel() }}</span>
                                <span class="mt-1 block text-[0.9375rem] text-ink-600">{{ __('services.taglines.'.$other->value) }}</span>
                            </span>
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ivory-100 text-ink-700 transition duration-500 group-hover:translate-x-1 group-hover:bg-teal-900 group-hover:text-paper rtl:group-hover:-translate-x-1">{!! $arrow !!}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-12">
                @include('site.partials.pricing-calculator', ['picked' => [$key]])
            </div>
        </div>
    </section>

    {{-- Questions --}}
    <section id="faq" class="scroll-mt-28 px-4 py-16 sm:px-6 lg:py-24">
        <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4" data-reveal>
                <p class="eyebrow"><span class="size-1.5 rounded-full bg-brass-500"></span>{{ __('site.faq.eyebrow') }}</p>
                <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 rtl:leading-[1.4]">{{ __('site.service_page.faq_title', ['service' => $service->getLabel()]) }}</h2>
            </div>
            <div class="lg:col-span-8">
                @include('site.partials.faq-list', ['items' => $faq])
            </div>
        </div>
    </section>

    @include('partials.request-access', ['services' => $forSale, 'preselect' => [$key]])
</x-site.page>
