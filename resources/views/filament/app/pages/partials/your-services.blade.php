@php
    use App\Domain\Services\Enums\ServiceStatus;
    use App\Support\Money;
    ['services' => $services, 'quote' => $quote, 'currency' => $currency] = $this->services();
    $tz = $this->restaurant()->timezone;
@endphp
<x-filament::section icon="heroicon-o-squares-2x2" :heading="__('services.owner.title')" :description="__('services.owner.subheading')">
    <ul class="grid gap-4 md:grid-cols-3">
        @foreach ($services as $item)
            @php
                $service = $item['service'];
                $row = $item['row'];
                $status = $row?->status;
                $on = $row?->isOn() ?? false;
                $unit = $row->unit_price_minor ?? $item['list_minor'];
            @endphp
            <li wire:key="service-{{ $service->value }}" @class([
                'flex flex-col rounded-[1.375rem] p-1.5 ring-1 transition',
                'bg-primary-50/60 ring-primary-600/15 dark:bg-primary-400/5 dark:ring-primary-400/20' => $on,
                'bg-gray-50 ring-gray-950/5 dark:bg-white/[0.03] dark:ring-white/10' => ! $on,
            ])>
                <div class="flex flex-1 flex-col rounded-[calc(1.375rem-0.375rem)] bg-white p-5 shadow-[0_1px_2px_rgb(0_0_0/0.04)] dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <span @class([
                            'grid size-11 shrink-0 place-items-center rounded-2xl ring-1',
                            'bg-primary-600 text-white ring-primary-700/20' => $on,
                            'bg-gray-100 text-gray-600 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => ! $on,
                        ])>
                            <x-filament::icon :icon="$service->getIcon()" class="size-5" />
                        </span>
                        @if ($status && $status !== ServiceStatus::Ended)
                            <x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge>
                        @endif
                    </div>

                    <h3 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">{{ $service->getLabel() }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('services.taglines.'.$service->value) }}</p>

                    <p class="mt-4 flex items-baseline gap-1.5">
                        @if ($on && $unit === 0)
                            <span class="text-xl font-semibold text-gray-950 dark:text-white">{{ __('services.included') }}</span>
                        @else
                            <span class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ Money::format($unit, $currency) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('services.per_branch_month') }}</span>
                        @endif
                    </p>

                    <div class="mt-3 min-h-5 text-xs">
                        @if ($status === ServiceStatus::Demo)
                            <p class="flex items-center gap-1.5 font-medium text-info-700 dark:text-info-300">
                                <x-filament::icon icon="heroicon-m-clock" class="size-4" />
                                {{ trans_choice('services.demo_left', (int) $row->demoDaysLeft(), ['count' => $row->demoDaysLeft()]) }}
                                @if ($row->keep_after_demo) · {{ __('services.owner.kept') }} @endif
                            </p>
                        @elseif ($status === ServiceStatus::Cancelling)
                            <p class="font-medium text-warning-700 dark:text-warning-300">{{ __('services.ends_on', ['date' => $row->ends_at?->timezone($tz)->format('Y-m-d')]) }}</p>
                        @elseif (! $on && $item['for_sale'] && $row?->demo_used_at === null)
                            <p class="text-gray-500 dark:text-gray-400">{{ __('services.free_demo', ['days' => $item['demo_days']]) }}</p>
                        @elseif (! $on && $row?->demo_used_at !== null)
                            <p class="text-gray-500 dark:text-gray-400">{{ __('services.owner.demo_used', ['service' => $service->getLabel()]) }}</p>
                        @endif
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-2 pt-5">
                        @if (! $on && $item['for_sale'])
                            @if ($row?->demo_used_at === null)
                                {{ ($this->tryServiceAction)(['service' => $service->value]) }}
                            @endif
                            {{ ($this->addServiceAction)(['service' => $service->value]) }}
                        @elseif ($status === ServiceStatus::Demo)
                            @if ($row->keep_after_demo)
                                {{ ($this->dontKeepServiceAction)(['service' => $service->value]) }}
                            @else
                                {{ ($this->keepServiceAction)(['service' => $service->value]) }}
                                {{ ($this->cancelServiceAction)(['service' => $service->value]) }}
                            @endif
                        @elseif ($status === ServiceStatus::Active)
                            {{ ($this->cancelServiceAction)(['service' => $service->value]) }}
                        @elseif ($status === ServiceStatus::Cancelling)
                            {{ ($this->resumeServiceAction)(['service' => $service->value]) }}
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    <div class="mt-5 flex flex-wrap items-end justify-between gap-4 rounded-2xl bg-gray-50 px-5 py-4 dark:bg-white/5">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('services.owner.monthly_total') }} · {{ trans_choice('services.owner.branches', $quote['branches'], ['count' => $quote['branches']]) }}</p>
            @if ($quote['lines'] === [])
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('services.owner.nothing') }}</p>
            @elseif ($quote['discount_minor'] > 0)
                <p class="mt-1 text-xs text-success-700 dark:text-success-400">
                    {{ __('services.owner.bundle', ['percent' => rtrim(rtrim(number_format($quote['discount_bp'] / 100, 2, '.', ''), '0'), '.')]) }}
                    <span dir="ltr">−{{ Money::format($quote['discount_minor'], $currency) }}</span>
                </p>
            @endif
        </div>
        <p class="text-2xl font-semibold tabular-nums tracking-tight text-gray-950 dark:text-white" dir="ltr">{{ Money::format($quote['total_minor'], $currency) }}</p>
    </div>
</x-filament::section>
