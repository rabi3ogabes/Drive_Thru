@php
    use App\Domain\Billing\Enums\SubscriptionStatus;
    use App\Support\Digits;
    use App\Support\Money;
    /** @var \App\Domain\Tenancy\Models\Restaurant $record */
    $subscription = $record->subscription;
    $plan = $subscription?->plan;
    $status = $record->status;
    $other = app()->getLocale() === 'ar' ? 'en' : 'ar';
    $accent = $record->accent_color ?: '#0f3b3a';
    $trialLeft = $subscription?->trialDaysLeft();
@endphp
<div class="flex h-full w-full flex-col gap-5 p-1">
    <div class="flex items-start gap-3.5">
        @if ($logo = $record->getFilamentAvatarUrl())
            <img src="{{ $logo }}" alt="" class="size-12 shrink-0 rounded-2xl object-cover ring-1 ring-gray-950/5 dark:ring-white/10">
        @else
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl text-base font-semibold text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.15)]"
                  style="background: linear-gradient(145deg, {{ $accent }}, color-mix(in oklab, {{ $accent }} 65%, black));">
                {{ $record->initials() }}
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="truncate text-[0.9375rem] font-semibold text-gray-950 dark:text-white">{{ $record->name }}</p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400" lang="{{ $other }}"><bdi>{{ $record->getTranslation('name', $other, false) }}</bdi></p>
        </div>
        <x-filament::badge :color="$status->getColor()" size="sm">{{ $status->getLabel() }}</x-filament::badge>
    </div>

    <dl class="grid w-full grid-cols-3 gap-2 rounded-2xl bg-gray-50 p-3 text-center dark:bg-white/5">
        <div>
            <dt class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('clients.card.plan') }}</dt>
            <dd class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $plan?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('clients.card.branches') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number($subscription?->quantity ?? 0) }}</dd>
        </div>
        <div>
            <dt class="text-[0.6875rem] text-gray-500 dark:text-gray-400">{{ __('clients.card.team') }}</dt>
            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number((int) ($record->active_employees_count ?? 0)) }}</dd>
        </div>
    </dl>

    <div class="mt-auto flex w-full flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1.5">
            @if ($subscription?->status === SubscriptionStatus::Trialing && $trialLeft !== null)
                <x-filament::icon icon="heroicon-m-sparkles" class="size-4 text-info-500" />
                {{ trans_choice('clients.card.trial_left', $trialLeft, ['count' => $trialLeft]) }}
            @elseif ($subscription?->status === SubscriptionStatus::PastDue)
                <x-filament::icon icon="heroicon-m-clock" class="size-4 text-warning-500" />
                {{ __('clients.card.past_due') }}
            @else
                <x-filament::icon icon="heroicon-m-calendar" class="size-4" />
                {{ __('clients.card.since', ['date' => $record->created_at?->translatedFormat('M Y')]) }}
            @endif
        </span>
        @if ($subscription && $subscription->amountPerPeriodMinor() > 0)
            <span class="font-medium tabular-nums text-gray-700 dark:text-gray-200">
                {{ Money::format($subscription->amountPerPeriodMinor(), $subscription->currency) }}<span class="text-gray-400">/{{ $subscription->interval->value === 'year' ? __('clients.card.yr') : __('clients.card.mo') }}</span>
            </span>
        @endif
    </div>
</div>
