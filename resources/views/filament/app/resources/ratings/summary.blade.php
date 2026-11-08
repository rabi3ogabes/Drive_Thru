@php
    use App\Support\Digits;
    $count = $stats['count'];
    $low = $stats['distribution'][1] + $stats['distribution'][2];
    $happy = $stats['distribution'][4] + $stats['distribution'][5];
    $max = max(1, max($stats['distribution']));
    $days = $stats['days'];
    $dayMax = max(1, max(array_column($days, 'count')));
    $barWidth = 100 / max(1, count($days));
    $tagMax = max(1, $stats['tags'] === [] ? 1 : max($stats['tags']));
@endphp
<div class="space-y-4">
    {{-- The period in four numbers --}}
    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl bg-white px-4 py-3.5 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('feedback.admin.summary.average') }}</dt>
            <dd class="mt-1 flex items-baseline gap-1.5">
                <span class="text-2xl font-semibold tabular-nums tracking-tight text-gray-950 dark:text-white">{{ $stats['average'] !== null ? number_format($stats['average'], 1) : '—' }}</span>
                @if ($stats['average'] !== null)<span class="text-warning-500" aria-hidden="true">★</span>@endif
            </dd>
        </div>
        <div class="rounded-xl bg-white px-4 py-3.5 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('feedback.admin.summary.count') }}</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-gray-950 dark:text-white">{{ Digits::number($count) }}</dd>
        </div>
        <div class="rounded-xl bg-white px-4 py-3.5 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('feedback.admin.summary.happy') }}</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-success-600 dark:text-success-400">{{ $count > 0 ? round($happy / $count * 100).'%' : '—' }}</dd>
        </div>
        <div class="rounded-xl bg-white px-4 py-3.5 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('feedback.admin.summary.low') }}</dt>
            <dd @class(['mt-1 text-2xl font-semibold tabular-nums tracking-tight', 'text-danger-600 dark:text-danger-400' => $low > 0, 'text-gray-950 dark:text-white' => $low === 0])>{{ Digits::number($low) }}</dd>
        </div>
    </dl>

    @if ($count > 0)
        <div class="grid gap-4 lg:grid-cols-3">
            {{-- How many gave each score --}}
            <section class="rounded-xl bg-white p-4 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" aria-labelledby="dist-title">
                <h3 id="dist-title" class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('feedback.admin.summary.distribution') }}</h3>
                <ul class="mt-3 space-y-2">
                    @foreach ([5, 4, 3, 2, 1] as $score)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-8 shrink-0 tabular-nums text-gray-600 dark:text-gray-300" dir="ltr">{{ $score }} ★</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <span @class(['block h-full rounded-full', 'bg-success-500' => $score >= 4, 'bg-warning-500' => $score === 3, 'bg-danger-500' => $score <= 2])
                                      style="width: {{ round($stats['distribution'][$score] / $max * 100) }}%"></span>
                            </span>
                            <span class="w-8 shrink-0 text-end tabular-nums text-gray-500 dark:text-gray-400">{{ Digits::number($stats['distribution'][$score]) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- What guests mention most --}}
            <section class="rounded-xl bg-white p-4 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" aria-labelledby="tags-title">
                <h3 id="tags-title" class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('feedback.admin.summary.tags') }}</h3>
                @if ($stats['tags'] === [])
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('feedback.admin.summary.no_tags') }}</p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach (array_slice($stats['tags'], 0, 6, true) as $tag => $times)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="w-28 shrink-0 truncate text-gray-700 dark:text-gray-200">{{ __('feedback.tags.'.$tag) }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <span @class(['block h-full rounded-full', 'bg-primary-500' => in_array($tag, \App\Domain\Feedback\RatingTags::POSITIVE, true), 'bg-gray-400 dark:bg-gray-500' => ! in_array($tag, \App\Domain\Feedback\RatingTags::POSITIVE, true)])
                                          style="width: {{ round($times / $tagMax * 100) }}%"></span>
                                </span>
                                <span class="w-8 shrink-0 text-end tabular-nums text-gray-500 dark:text-gray-400">{{ Digits::number($times) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Ratings per day, coloured by that day's average --}}
            <section class="rounded-xl bg-white p-4 shadow-xs ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" aria-labelledby="trend-title">
                <h3 id="trend-title" class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('feedback.admin.summary.trend') }}</h3>
                <svg class="mt-3 h-28 w-full" viewBox="0 0 100 40" preserveAspectRatio="none" dir="ltr" role="img" aria-label="{{ __('feedback.admin.summary.trend') }}">
                    @foreach ($days as $i => $day)
                        @continue($day['count'] === 0)
                        @php
                            $height = max(2, $day['count'] / $dayMax * 38);
                            $tone = $day['average'] >= 4 ? 'fill-success-500' : ($day['average'] >= 3 ? 'fill-warning-500' : 'fill-danger-500');
                        @endphp
                        <rect class="{{ $tone }}" x="{{ $i * $barWidth + $barWidth * 0.18 }}" y="{{ 40 - $height }}" width="{{ $barWidth * 0.64 }}" height="{{ $height }}" rx="0.6">
                            <title>{{ \Carbon\CarbonImmutable::parse($day['date'])->translatedFormat('j M') }} · {{ Digits::number($day['count']) }} · {{ number_format((float) $day['average'], 1) }} ★</title>
                        </rect>
                    @endforeach
                </svg>
                <div class="mt-1 flex justify-between text-xs text-gray-400" dir="ltr">
                    <span>{{ \Carbon\CarbonImmutable::parse($days[0]['date'])->translatedFormat('j M') }}</span>
                    <span>{{ \Carbon\CarbonImmutable::parse($days[array_key_last($days)]['date'])->translatedFormat('j M') }}</span>
                </div>
            </section>
        </div>
    @endif
</div>
