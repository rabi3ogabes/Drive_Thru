@php
    use App\Support\Money;
    $currency = $branch->restaurant->currency;
    $pct = fn (int $now, int $then): ?int => $then > 0 ? (int) round(($now - $then) / $then * 100) : null;
    $vsYesterday = $pct($pulse['sales_today'], $pulse['sales_yesterday']);
    $vsLastWeek = $pct($pulse['sales_today'], $pulse['sales_last_week']);

    // The chart covers the hours that have sales on either day (at least 8 hours, up to now).
    $active = array_keys(array_filter($pulse['hourly'], fn (array $h): bool => $h['today'] > 0 || $h['yesterday'] > 0));
    $nowHour = (int) now($branch->restaurant->timezone)->format('G');
    $from = $active === [] ? max(0, $nowHour - 7) : min(min($active), max(0, $nowHour - 7));
    $to = max($nowHour, $active === [] ? $nowHour : max($active), $from + 7);
    $to = min(23, $to);
    $hours = range($from, $to);
    $peak = max(1, ...array_map(fn (int $h): int => max($pulse['hourly'][$h]['today'], $pulse['hourly'][$h]['yesterday']), $hours));
    $barWidth = 100 / count($hours);

    $tiles = [
        [__('ops.tv.in_kitchen'), $pulse['new'] + $pulse['preparing'], 'text-status-progress'],
        [__('ops.tv.ready'), $pulse['ready'], 'text-status-ready'],
        [__('ops.tv.delayed'), $pulse['delayed'], $pulse['delayed'] > 0 ? 'text-status-delayed' : 'text-white'],
        [__('ops.tv.waiting'), $pulse['arrived_waiting'], 'text-ice-200'],
    ];
    $tone = ['critical' => 'bg-status-delayed/18 ring-status-delayed/40', 'warning' => 'bg-status-progress/14 ring-status-progress/35', 'info' => 'bg-white/6 ring-white/12'];
@endphp
<div wire:poll.30s>
    <x-ops.shell :branch="$branch" :title="__('ops.screens.tv')" :device-id="$deviceId" screen="tv">
        <div class="grid gap-5 p-5 lg:p-8 2xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div class="space-y-5">
                {{-- Big counts --}}
                <section class="grid grid-cols-2 gap-4 xl:grid-cols-4" aria-label="{{ __('ops.tv.now') }}">
                    @foreach ($tiles as [$label, $value, $color])
                        <div class="rounded-[2rem] bg-white/[0.045] px-6 py-5 ring-1 ring-white/8">
                            <p class="text-xl text-white/55">{{ $label }}</p>
                            <p class="mt-1 text-[clamp(3rem,6vw,6rem)] font-semibold leading-none tabular-nums {{ $color }}">{{ $value }}</p>
                        </div>
                    @endforeach
                </section>

                {{-- Sales --}}
                <section class="rounded-[2rem] bg-white/[0.045] p-6 ring-1 ring-white/8" aria-labelledby="sales-title">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 id="sales-title" class="text-xl text-white/55">{{ __('ops.tv.sales_today') }}</h2>
                            <p class="mt-1 text-[clamp(2.5rem,4.5vw,4.5rem)] font-semibold leading-none tabular-nums" dir="ltr">{{ Money::format($pulse['sales_today'], $currency) }}</p>
                            <p class="mt-2 text-lg text-white/55">{{ trans_choice('ops.tv.orders', $pulse['orders_today'], ['count' => $pulse['orders_today']]) }} · {{ __('ops.tv.average_prep', ['minutes' => $pulse['average_prep'] ?? '—']) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @foreach ([[__('ops.tv.vs_yesterday'), $vsYesterday], [__('ops.tv.vs_last_week'), $vsLastWeek]] as [$label, $change])
                                <div class="rounded-2xl bg-white/5 px-5 py-3 ring-1 ring-white/8">
                                    <p class="text-sm text-white/50">{{ $label }}</p>
                                    <p @class(['text-2xl font-semibold tabular-nums', 'text-status-ready' => $change !== null && $change >= 0, 'text-status-delayed' => $change !== null && $change < 0, 'text-white/40' => $change === null]) dir="ltr">
                                        {{ $change === null ? '—' : ($change >= 0 ? '▲ +' : '▼ ').$change.'%' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <figure class="mt-6">
                        <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-56 w-full" role="img" aria-label="{{ __('ops.tv.chart') }}">
                            @foreach ($hours as $i => $hour)
                                @php
                                    $y = $pulse['hourly'][$hour]['yesterday'] / $peak * 36;
                                    $t = $pulse['hourly'][$hour]['today'] / $peak * 36;
                                    $x = $i * $barWidth;
                                @endphp
                                <rect x="{{ $x + $barWidth * 0.12 }}" y="{{ 38 - $y }}" width="{{ $barWidth * 0.76 }}" height="{{ max($y, 0.0001) }}" rx="0.6" class="fill-white/12" />
                                <rect x="{{ $x + $barWidth * 0.26 }}" y="{{ 38 - $t }}" width="{{ $barWidth * 0.48 }}" height="{{ max($t, 0.0001) }}" rx="0.6" class="fill-brass-300" />
                            @endforeach
                        </svg>
                        <div class="mt-2 grid text-center text-sm tabular-nums text-white/45" style="grid-template-columns: repeat({{ count($hours) }}, minmax(0, 1fr))" dir="ltr">
                            @foreach ($hours as $hour)<span>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</span>@endforeach
                        </div>
                        <figcaption class="mt-3 flex gap-5 text-base text-white/60">
                            <span class="inline-flex items-center gap-2"><span class="size-3 rounded-sm bg-brass-300"></span>{{ __('ops.tv.today') }}</span>
                            <span class="inline-flex items-center gap-2"><span class="size-3 rounded-sm bg-white/25"></span>{{ __('ops.tv.yesterday') }}</span>
                        </figcaption>
                    </figure>
                </section>
            </div>

            <div class="space-y-5">
                {{-- Ready for pickup --}}
                <section class="rounded-[2rem] bg-white/[0.045] p-6 ring-1 ring-white/8" aria-labelledby="ready-title">
                    <h2 id="ready-title" class="text-xl text-white/55">{{ __('ops.tv.ready_for_pickup') }}</h2>
                    @if ($readyNumbers->isEmpty())
                        <p class="mt-4 text-2xl text-white/35">{{ __('ops.tv.none_ready') }}</p>
                    @else
                        <ul class="mt-4 grid grid-cols-3 gap-3">
                            @foreach ($readyNumbers as $order)
                                <li class="rounded-2xl bg-status-ready/14 py-4 text-center text-3xl font-semibold tabular-nums text-sage-100 ring-1 ring-status-ready/30">{{ $order->displayNumber() }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- Alerts --}}
                <section class="rounded-[2rem] bg-white/[0.045] p-6 ring-1 ring-white/8" aria-labelledby="alerts-title" aria-live="polite">
                    <h2 id="alerts-title" class="text-xl text-white/55">{{ __('ops.tv.alerts') }}</h2>
                    @if ($pulse['alerts'] === [])
                        <p class="mt-4 flex items-center gap-3 text-2xl text-sage-100">
                            <svg class="size-8" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('ops.tv.all_good') }}
                        </p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($pulse['alerts'] as $alert)
                                <li class="flex items-center gap-3 rounded-2xl px-4 py-3 text-xl ring-1 {{ $tone[$alert['tone']] }}">
                                    <span class="shrink-0 rounded-full bg-ink-950/40 px-3 py-1 text-sm font-semibold uppercase tracking-wider rtl:tracking-normal">{{ __('ops.alert_tone.'.$alert['tone']) }}</span>
                                    <span>{{ __('ops.alerts.'.$alert['key'], ['number' => $alert['number'] ?? '', 'minutes' => $alert['minutes'] ?? 0, 'count' => $alert['minutes'] ?? 0]) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </x-ops.shell>
</div>
