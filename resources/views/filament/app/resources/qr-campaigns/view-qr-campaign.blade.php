@php
    use App\Support\Bidi;
    use App\Support\Digits;
    use App\Support\Money;

    $max = max(1, ...array_map(fn (array $d): int => max($d['scans'], $d['orders']), $funnel['days']));
    $hasData = $funnel['scans'] > 0 || $funnel['orders'] > 0;
    $steps = [
        ['key' => 'scans', 'value' => $funnel['scans']],
        ['key' => 'visits', 'value' => $funnel['visits']],
        ['key' => 'orders', 'value' => $funnel['orders']],
    ];
    $top = max(1, $funnel['scans']);
    $count = count($funnel['days']);
    // Chart geometry (viewBox units): two thin bars per day.
    $w = 600; $h = 160; $gap = $w / $count; $bar = max(2, $gap * 0.32);
@endphp
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- The code and its link --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mx-auto w-full max-w-[13rem] rounded-2xl bg-white p-3 ring-1 ring-gray-950/5 [&_svg]:h-auto [&_svg]:w-full" aria-label="QR">{!! $svg !!}</div>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                <x-filament::badge :color="match ($status) { 'live' => 'success', 'scheduled' => 'info', default => 'gray' }">{{ __('qr.status.'.$status) }}</x-filament::badge>
                <span class="font-mono text-sm font-semibold tracking-wider text-gray-950 dark:text-white" dir="ltr">{{ $campaign->code }}</span>
            </div>
            <div class="mt-4 flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5" x-data="{ copied: false }">
                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200" dir="ltr">{{ $campaign->url() }}</span>
                <button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-white/5"
                        x-on:click="navigator.clipboard?.writeText(@js($campaign->url())); copied = true; setTimeout(() => copied = false, 1800)">
                    <span x-show="!copied">{{ __('qr.copy') }}</span><span x-show="copied" x-cloak>{{ __('qr.copied') }}</span>
                </button>
            </div>
            <dl class="mt-4 grid gap-2 text-sm">
                @if ($campaign->placement)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('qr.fields.placement') }}</dt><dd class="text-end text-gray-900 dark:text-gray-100">{{ $campaign->placement }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('qr.fields.branch') }}</dt><dd class="text-end text-gray-900 dark:text-gray-100">{{ $campaign->branch?->name ?? __('qr.fields.restaurant_page') }}</dd></div>
                @if ($campaign->lane)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('qr.fields.lane') }}</dt><dd class="text-end text-gray-900 dark:text-gray-100">{{ $campaign->lane }}</dd></div>
                @endif
                @if ($campaign->promo)
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('qr.fields.promo') }}</dt><dd class="font-mono text-end text-gray-900 dark:text-gray-100" dir="ltr">{{ $campaign->promo }}</dd></div>
                @endif
            </dl>
        </section>

        {{-- What it brought --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('qr.funnel.title') }}</h2>

            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach ([
                    ['scans', Digits::number($funnel['scans'])],
                    ['visits', Digits::number($funnel['visits'])],
                    ['orders', Digits::number($funnel['orders'])],
                    ['revenue', Money::format($funnel['revenue_minor'], $currency)],
                    ['conversion', $funnel['conversion'] === null ? '—' : Digits::number($funnel['conversion'], 1).'%'],
                ] as [$key, $value])
                    <div @class(['rounded-2xl bg-gray-50 p-3.5 dark:bg-white/5', 'col-span-2 sm:col-span-1' => $key === 'conversion'])>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('qr.funnel.'.$key) }}</dt>
                        <dd class="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $value }}</dd>
                        <dd class="mt-0.5 text-[0.6875rem] leading-snug text-gray-400 dark:text-gray-500">{{ __('qr.funnel.'.$key.'_hint') }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- The funnel, as bars that narrow step by step --}}
            <ol class="mt-6 grid gap-2.5">
                @foreach ($steps as $step)
                    <li class="grid grid-cols-[5.5rem_1fr_3rem] items-center gap-3 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">{{ __('qr.funnel.'.$step['key']) }}</span>
                        <span class="h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                            <span class="block h-full rounded-full bg-primary-500" style="inline-size: {{ $step['value'] > 0 ? max(3, round($step['value'] / $top * 100)) : 0 }}%"></span>
                        </span>
                        <span class="text-end tabular-nums text-gray-950 dark:text-white">{{ Digits::number($step['value']) }}</span>
                    </li>
                @endforeach
            </ol>

            {{-- The last 30 days --}}
            <div class="mt-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('qr.funnel.days') }}</h3>
                    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary-500"></span>{{ __('qr.funnel.legend_scans') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-warning-500"></span>{{ __('qr.funnel.legend_orders') }}</span>
                    </div>
                </div>
                @if (! $hasData)
                    <p class="mt-4 rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">{{ __('qr.funnel.no_data') }}</p>
                @else
                    {{-- Time runs left to right in both languages, like the dates under it. --}}
                    <div dir="ltr" class="mt-4">
                        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-40 w-full" preserveAspectRatio="none" role="img" aria-label="{{ __('qr.funnel.days') }}">
                            @foreach ([0.25, 0.5, 0.75] as $line)
                                <line x1="0" x2="{{ $w }}" y1="{{ $h - $h * $line }}" y2="{{ $h - $h * $line }}" class="stroke-gray-100 dark:stroke-white/5" stroke-width="1" vector-effect="non-scaling-stroke"/>
                            @endforeach
                            @foreach ($funnel['days'] as $i => $day)
                                @php
                                    $x = $i * $gap + $gap / 2;
                                    $sh = $day['scans'] / $max * ($h - 6);
                                    $oh = $day['orders'] / $max * ($h - 6);
                                @endphp
                                <g>
                                    <title>{{ __('qr.funnel.day', ['date' => $day['date'], 'scans' => $day['scans'], 'orders' => $day['orders']]) }}</title>
                                    <rect x="{{ $x - $bar - 0.5 }}" y="{{ $h - $sh }}" width="{{ $bar }}" height="{{ $sh }}" rx="1.5" class="fill-primary-500"/>
                                    <rect x="{{ $x + 0.5 }}" y="{{ $h - $oh }}" width="{{ $bar }}" height="{{ $oh }}" rx="1.5" class="fill-warning-500"/>
                                </g>
                            @endforeach
                        </svg>
                        <div class="mt-1.5 flex justify-between text-[0.6875rem] tabular-nums text-gray-400 dark:text-gray-500">
                            <span>{{ Bidi::ltr(\Carbon\CarbonImmutable::parse($funnel['days'][0]['date'])->locale(app()->getLocale())->translatedFormat('j M')) }}</span>
                            <span>{{ Bidi::ltr(\Carbon\CarbonImmutable::parse($funnel['days'][$count - 1]['date'])->locale(app()->getLocale())->translatedFormat('j M')) }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
