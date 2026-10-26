@php
    use App\Domain\Billing\Enums\InvoiceStatus;
    use App\Domain\Billing\Enums\SubscriptionStatus;
    use App\Support\Digits;
    use App\Support\Money;
    $restaurant = $this->restaurant();
    $subscription = $restaurant->subscription;
    $plan = $subscription?->plan;
    $due = \App\Domain\Billing\Models\Invoice::query()->where('restaurant_id', $restaurant->id)->where('status', InvoiceStatus::Open)->orderBy('due_at')->first();
@endphp
<x-filament-panels::page>
    @include('filament.app.pages.partials.your-services')

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-2" icon="heroicon-o-sparkles" :heading="__('billing.owner.your_plan')">
            @if ($subscription && $plan)
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p class="font-serif text-3xl font-medium tracking-tight text-gray-950 dark:text-white">{{ $plan->name }}</p>
                        @if ($plan->description)
                            <p class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ $plan->description }}</p>
                        @endif
                    </div>
                    <x-filament::badge :color="$subscription->status->getColor()" :icon="$subscription->status->getIcon()" size="lg">
                        {{ $subscription->status->getLabel() }}
                    </x-filament::badge>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-3 2xl:grid-cols-4">
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('clients.fields.branches') }}</dt>
                        <dd class="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number($subscription->quantity) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('clients.fields.period_amount') }}</dt>
                        <dd class="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ Money::format($subscription->amountPerPeriodMinor(), $subscription->currency) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $subscription->status === SubscriptionStatus::Trialing ? __('clients.fields.trial_ends') : __('clients.fields.next_invoice') }}
                        </dt>
                        <dd class="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $subscription->current_period_end?->timezone($restaurant->timezone)->format('Y-m-d') ?? '—' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('billing.owner.billed') }}</dt>
                        <dd class="mt-1 whitespace-nowrap text-lg font-semibold text-gray-950 dark:text-white">{{ $subscription->interval->getLabel() }}</dd>
                    </div>
                </dl>

                @if (($left = $subscription->trialDaysLeft()) !== null)
                    <p class="mt-5 flex items-center gap-2 text-sm text-info-700 dark:text-info-300">
                        <x-filament::icon icon="heroicon-m-sparkles" class="size-4" />
                        {{ trans_choice('billing.owner.trial_left', $left, ['count' => $left]) }}
                    </p>
                @endif
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing.owner.no_plan') }}</p>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-banknotes" :heading="__('billing.owner.how_to_pay')">
            @if ($due)
                <div @class([
                    'rounded-2xl p-4 ring-1',
                    'bg-danger-50 ring-danger-600/10 dark:bg-danger-400/10' => $due->isOverdue(),
                    'bg-warning-50 ring-warning-600/10 dark:bg-warning-400/10' => ! $due->isOverdue(),
                ])>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $due->isOverdue() ? __('billing.owner.overdue', ['number' => $due->number]) : __('billing.owner.due', ['number' => $due->number]) }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $due->formattedTotal() }}</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300" dir="ltr">{{ __('billing.fields.due') }} · {{ $due->due_at->timezone($restaurant->timezone)->format('Y-m-d') }}</p>
                </div>
            @else
                <p class="flex items-center gap-2 text-sm text-success-700 dark:text-success-400">
                    <x-filament::icon icon="heroicon-m-check-circle" class="size-5" />
                    {{ __('billing.owner.all_paid') }}
                </p>
            @endif

            @if ($bank = $this->bankDetails())
                <div class="mt-4 text-sm">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('platform.settings.fields.bank_details') }}</p>
                    <p class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-200" dir="auto">{{ $bank }}</p>
                </div>
            @endif

            @if ($email = $this->billingEmail())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('billing.owner.questions') }}
                    <a href="mailto:{{ $email }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400" dir="ltr">{{ $email }}</a>
                </p>
            @endif
        </x-filament::section>
    </div>

    <div class="mt-2">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
