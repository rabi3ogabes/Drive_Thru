@php
    use App\Domain\Branches\BranchSchedule;
    use App\Domain\Branches\Enums\BranchState;
    use App\Support\Digits;
    /** @var \App\Domain\Branches\Models\Branch $record */
    $schedule = app(BranchSchedule::class);
    $state = $schedule->state($record);
    $tz = $record->restaurant->timezone;
    $other = app()->getLocale() === 'ar' ? 'en' : 'ar';

    $detail = match (true) {
        $state === BranchState::Paused => __('branches.card.paused_until', ['time' => $schedule->reopensAt($record)?->translatedFormat('D H:i') ?? '—']),
        $state === BranchState::Busy => __('branches.card.busy', ['minutes' => $record->busy_extra_minutes, 'time' => $record->busy_until?->setTimezone($tz)->format('H:i')]),
        $state->acceptsOrders() => ($closes = $schedule->closesAt($record)) ? __('branches.card.closes_at', ['time' => $closes->format('H:i')]) : __('branches.card.all_day'),
        ($opens = $schedule->reopensAt($record)) !== null => __('branches.card.opens_at', ['time' => $opens->translatedFormat('D H:i')]),
        default => null,
    };
@endphp
<div class="flex h-full w-full flex-col gap-5 p-1">
    <div class="flex items-start gap-3.5">
        @if ($logo = $record->logoUrl())
            <img src="{{ $logo }}" alt="" class="size-12 shrink-0 rounded-2xl object-cover ring-1 ring-gray-950/5 dark:ring-white/10">
        @else
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                <x-filament::icon icon="heroicon-o-building-storefront" class="size-6" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="line-clamp-2 text-[0.9375rem] font-semibold leading-snug text-gray-950 dark:text-white">{{ $record->name }}</p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                <bdi lang="{{ $other }}">{{ $record->getTranslation('name', $other, false) }}</bdi>
                @if ($record->city) · {{ $record->city }} @endif
            </p>
        </div>
        <x-filament::badge :color="$state->getColor()" :icon="$state->getIcon()" size="sm">{{ $state->getLabel() }}</x-filament::badge>
    </div>

    <dl class="grid w-full grid-cols-3 gap-2 rounded-2xl bg-gray-50 p-3 text-center dark:bg-white/5">
        <div>
            <dt class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('branches.card.prep') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ __('branches.card.minutes', ['count' => Digits::number($record->currentPrepMinutes())]) }}</dd>
        </div>
        <div>
            <dt class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('branches.card.lanes') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number((int) ($record->active_lanes_count ?? 0)) }}</dd>
        </div>
        <div>
            <dt class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('branches.card.screens') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number((int) ($record->active_devices_count ?? 0)) }}</dd>
        </div>
    </dl>

    <div class="mt-auto flex w-full flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1.5">
            <x-filament::icon icon="heroicon-m-clock" class="size-4" />
            {{ $detail ?? __('branches.card.no_hours') }}
        </span>
        <span class="font-medium text-gray-400 dark:text-gray-500" dir="ltr">/{{ $record->code }}</span>
    </div>
</div>
