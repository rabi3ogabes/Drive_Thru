@props(['branch', 'schedule', 'size' => 'sm'])
@php
    use App\Domain\Branches\Enums\BranchState;

    $state = $schedule->state($branch);
    $tz = $branch->restaurant->timezone;
    $now = now($tz);

    $detail = null;
    if ($state->acceptsOrders()) {
        $closes = $schedule->closesAt($branch);
        $detail = $closes !== null ? __('public.status.closes_at', ['time' => $closes->format('H:i')]) : __('public.status.all_day');
    } elseif (($opens = $schedule->reopensAt($branch)) && $state === BranchState::Paused && $opens->isSameDay($now)) {
        $detail = __('public.status.back_at', ['time' => $opens->format('H:i')]);
    } elseif ($opens) {
        $day = match (true) {
            $opens->isSameDay($now) => __('public.status.today'),
            $opens->isSameDay($now->addDay()) => __('public.status.tomorrow'),
            default => $opens->locale(app()->getLocale())->dayName,
        };
        $detail = __('public.status.opens_at', ['day' => $day, 'time' => $opens->format('H:i')]);
    }

    $tone = match ($state) {
        BranchState::Open => 'bg-sage-100 text-teal-900 ring-teal-700/15',
        BranchState::Busy => 'bg-brass-50 text-brass-900 ring-brass-500/25',
        BranchState::Paused => 'bg-ivory-200 text-ink-800 ring-ink-950/10',
        default => 'bg-ink-100 text-ink-700 ring-ink-950/8',
    };
    $dot = match ($state) {
        BranchState::Open => 'bg-status-ready',
        BranchState::Busy => 'bg-status-progress',
        default => 'bg-ink-400',
    };
@endphp
<div {{ $attributes->class(['flex flex-wrap items-center gap-x-3 gap-y-1.5']) }}>
    <span @class([
        'inline-flex items-center gap-2 rounded-full font-semibold ring-1',
        'px-3 py-1 text-xs' => $size === 'sm',
        'px-4 py-1.5 text-sm' => $size === 'lg',
        $tone,
    ])>
        <span class="relative flex size-2">
            @if ($state === BranchState::Open)
                <span class="absolute inline-flex size-full animate-ping rounded-full {{ $dot }} opacity-50"></span>
            @endif
            <span class="relative inline-flex size-2 rounded-full {{ $dot }}"></span>
        </span>
        {{ $state->getLabel() }}
    </span>
    @if ($detail)
        <span @class(['text-ink-600', 'text-sm' => $size === 'sm', 'text-base' => $size === 'lg'])>{{ $detail }}</span>
    @endif
</div>
