@php
    use App\Domain\Ordering\BranchPulse;
    use App\Support\Money;
    $currency = $restaurant->currency;
    $health = [
        BranchPulse::HEALTHY => ['bg-status-ready/15 text-sage-100 ring-status-ready/35', 'heroicon-o-check-circle'],
        BranchPulse::BUSY => ['bg-status-progress/15 text-brass-100 ring-status-progress/35', 'heroicon-o-clock'],
        BranchPulse::ATTENTION => ['bg-status-delayed/18 text-ice-50 ring-status-delayed/45', 'heroicon-o-exclamation-triangle'],
    ];
    $change = $totals['yesterday'] > 0 ? (int) round(($totals['sales'] - $totals['yesterday']) / $totals['yesterday'] * 100) : null;
@endphp
<div wire:poll.30s>
    <x-ops.shell :restaurant="$restaurant" :title="__('ops.screens.overview')" :channel="'restaurant.'.$restaurant->id.'.dashboard'" screen="overview">
        <section class="grid grid-cols-2 gap-4 p-4 sm:p-6 lg:grid-cols-4" aria-label="{{ __('ops.exec.totals') }}">
            <div class="rounded-[2rem] bg-white/[0.045] px-6 py-5 ring-1 ring-white/8">
                <p class="text-white/55">{{ __('ops.tv.sales_today') }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums" dir="ltr">{{ Money::format($totals['sales'], $currency) }}</p>
                @if ($change !== null)<p @class(['text-sm font-semibold tabular-nums', 'text-status-ready' => $change >= 0, 'text-status-delayed' => $change < 0])>{{ __('ops.board.vs_yesterday', ['change' => ($change >= 0 ? '+' : '').$change.'%']) }}</p>@endif
            </div>
            <div class="rounded-[2rem] bg-white/[0.045] px-6 py-5 ring-1 ring-white/8">
                <p class="text-white/55">{{ __('ops.board.kpi.orders_today') }}</p>
                <p class="mt-1 text-4xl font-semibold tabular-nums">{{ $totals['orders'] }}</p>
            </div>
            <div class="rounded-[2rem] bg-white/[0.045] px-6 py-5 ring-1 ring-white/8">
                <p class="text-white/55">{{ __('ops.exec.in_progress') }}</p>
                <p class="mt-1 text-4xl font-semibold tabular-nums">{{ $totals['open'] }}</p>
            </div>
            <div class="rounded-[2rem] bg-white/[0.045] px-6 py-5 ring-1 ring-white/8">
                <p class="text-white/55">{{ __('ops.board.kpi.delayed') }}</p>
                <p @class(['mt-1 text-4xl font-semibold tabular-nums', 'text-status-delayed' => $totals['delayed'] > 0])>{{ $totals['delayed'] }}</p>
            </div>
        </section>

        <section class="grid gap-4 px-4 pb-8 sm:px-6 md:grid-cols-2 2xl:grid-cols-3" aria-label="{{ __('ops.exec.branches') }}">
            @forelse ($branches as ['branch' => $branch, 'pulse' => $pulse, 'state' => $state])
                @php [$tone, $icon] = $health[$pulse['health']]; @endphp
                <article wire:key="b-{{ $branch->id }}" class="flex flex-col rounded-[2rem] bg-white/[0.045] p-6 ring-1 ring-white/8">
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-2xl font-semibold">{{ $branch->name }}</h2>
                            <p class="text-white/50">{{ $state->getLabel() }} · {{ __('ops.exec.quoted', ['minutes' => $pulse['estimate']]) }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-base font-semibold ring-1 {{ $tone }}">
                            <x-filament::icon :icon="$icon" class="size-5" />
                            {{ __('ops.health.'.$pulse['health']) }}
                        </span>
                    </header>

                    <dl class="mt-5 grid grid-cols-4 gap-2 text-center">
                        @foreach ([['new', $pulse['new']], ['preparing', $pulse['preparing']], ['ready', $pulse['ready']], ['delayed', $pulse['delayed']]] as [$key, $value])
                            <div class="rounded-2xl bg-white/5 py-3">
                                <dt class="text-xs text-white/50">{{ __('ops.board.kpi.'.$key) }}</dt>
                                <dd @class(['text-2xl font-semibold tabular-nums', 'text-status-delayed' => $key === 'delayed' && $value > 0])>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <p class="mt-4 flex items-baseline justify-between text-white/60">
                        <span>{{ __('ops.tv.sales_today') }}</span>
                        <span class="text-xl font-semibold tabular-nums text-white" dir="ltr">{{ Money::format($pulse['sales_today'], $currency) }}</span>
                    </p>

                    @if ($pulse['alerts'] !== [])
                        <ul class="mt-3 space-y-1.5 text-sm">
                            @foreach (array_slice($pulse['alerts'], 0, 3) as $alert)
                                <li class="rounded-xl bg-white/5 px-3 py-2 text-white/80">{{ __('ops.alerts.'.$alert['key'], ['number' => $alert['number'] ?? '', 'minutes' => $alert['minutes'] ?? 0, 'count' => $alert['minutes'] ?? 0]) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-auto grid grid-cols-2 gap-2 pt-5">
                        <a href="{{ route('ops.screen', [$restaurant, $branch->code, 'live']) }}" class="flex min-h-14 items-center justify-center rounded-2xl bg-white text-base font-semibold text-ink-950">{{ __('ops.screens.live') }}</a>
                        <a href="{{ route('ops.screen', [$restaurant, $branch->code, 'tv']) }}" class="flex min-h-14 items-center justify-center rounded-2xl bg-white/8 text-base font-semibold">{{ __('ops.screens.tv') }}</a>
                    </div>
                </article>
            @empty
                <p class="col-span-full rounded-3xl border border-dashed border-white/10 px-6 py-12 text-center text-lg text-white/40">{{ __('ops.exec.no_branches') }}</p>
            @endforelse
        </section>
    </x-ops.shell>
</div>
