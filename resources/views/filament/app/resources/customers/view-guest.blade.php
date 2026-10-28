@php
    use App\Support\Bidi;
    use App\Support\Digits;
    use App\Support\Money;
    $tz = $restaurant->timezone;
    $currency = $restaurant->currency;
    $date = fn ($at) => Bidi::ltr($at?->timezone($tz)->format('Y-m-d'));
    $initial = mb_strtoupper(mb_substr($guest->name ?? __('crm.guests.unnamed'), 0, 1));
    $segments = $profile['segments']->sortBy(fn ($row) => $row->segment->name)->values();
    $stats = [
        ['label' => __('crm.guests.fields.orders'), 'value' => Digits::number($guest->picked_up_count), 'hint' => __('crm.guests.profile.lifetime')],
        ['label' => __('crm.guests.profile.revenue'), 'value' => Money::format($profile['lifetime_minor'], $currency), 'hint' => __('crm.guests.profile.lifetime')],
        ['label' => __('crm.guests.fields.avg_order'), 'value' => $guest->avg_order_minor > 0 ? Money::format($guest->avg_order_minor, $currency) : '—', 'hint' => null],
        ['label' => __('crm.guests.fields.spent_90d'), 'value' => Money::format($guest->spent_90d_minor, $currency), 'hint' => trans_choice('crm.guests.profile.orders_90d', $guest->orders_90d, ['count' => Digits::number($guest->orders_90d)])],
    ];
@endphp
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        {{-- Identity --}}
        <section class="fi-section relative min-w-0 overflow-hidden p-5 sm:p-8">
            <div class="flex flex-wrap items-center gap-5">
                <span class="grid size-16 shrink-0 place-items-center rounded-full bg-primary-50 font-serif text-2xl font-medium text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20" aria-hidden="true">{{ $initial }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $phone }}</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if ($guest->first_order_at)
                            {{ __('crm.guests.profile.since', ['date' => $date($guest->first_order_at)]) }}
                            @if ($guest->last_order_at)
                                <span class="mx-1.5 text-gray-300 dark:text-gray-600">·</span>{{ __('crm.guests.fields.last_order') }} {{ $guest->last_order_at->diffForHumans() }}
                            @endif
                        @else
                            {{ __('crm.guests.profile.no_orders') }}
                        @endif
                    </p>
                    @if ($segments->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($segments as $row)
                                <x-filament::badge :color="$row->segment->getColor()" :tooltip="$row->segment->description($crm)">
                                    {{ $row->product ? __('crm.frequent_of', ['product' => $row->product->name]) : $row->segment->getLabel() }}
                                </x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($loyalty)
                    <div class="flex w-full items-center justify-between gap-4 rounded-2xl bg-gray-50 px-5 py-4 sm:block sm:w-auto sm:text-end dark:bg-white/5">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('crm.guests.fields.points') }}</p>
                            <p class="mt-0.5 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number($guest->points_balance) }}</p>
                        </div>
                        @if ($guest->tier)
                            <p class="mt-1 flex items-center gap-1 text-xs font-medium text-warning-700 sm:justify-end dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-star" class="size-3.5" />{{ $guest->tier->name }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($stats as $stat)
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</dt>
                        <dd class="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $stat['value'] }}</dd>
                        @if ($stat['hint'])
                            <dd class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $stat['hint'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="grid min-w-0 grid-cols-1 content-start gap-6 lg:col-span-3">
                {{-- Recent orders --}}
                <x-filament::section icon="heroicon-o-receipt-percent" :heading="__('crm.guests.profile.recent_orders')">
                    @if ($profile['recent']->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('crm.guests.profile.no_orders') }}</p>
                    @else
                        <ul class="-my-3 divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($profile['recent'] as $order)
                                <li class="flex items-center gap-3 py-3 sm:gap-4">
                                    <span class="w-12 shrink-0 font-mono text-sm font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">#{{ $order->number }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-gray-700 dark:text-gray-200">
                                            {{ $order->branch?->name }}
                                            <span class="text-gray-400 dark:text-gray-500">· {{ trans_choice('crm.guests.profile.items', (int) $order->quantity, ['count' => Digits::number((int) $order->quantity)]) }}</span>
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ Bidi::ltr(($order->placed_at ?? $order->created_at)?->timezone($tz)->format('Y-m-d H:i')) }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1 sm:flex-row-reverse sm:items-center sm:gap-4">
                                        <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-gray-950 sm:w-24 sm:text-end dark:text-white">{{ Money::format($order->total_minor, $order->currency) }}</span>
                                        <x-filament::badge :color="$order->status->getColor()" size="sm">{{ $order->status->getLabel() }}</x-filament::badge>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>

                {{-- Favourites --}}
                <x-filament::section icon="heroicon-o-heart" :heading="__('crm.guests.profile.favourites')">
                    @if ($profile['favourites'] === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('crm.guests.profile.no_favourites') }}</p>
                    @else
                        @php
                            $most = max(array_column($profile['favourites'], 'quantity'));
                        @endphp
                        <ol class="grid gap-3">
                            @foreach ($profile['favourites'] as $favourite)
                                <li>
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="truncate font-medium text-gray-800 dark:text-gray-100">{{ $favourite['product']->name }}</span>
                                        <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ trans_choice('crm.guests.profile.times', $favourite['quantity'], ['count' => Digits::number($favourite['quantity'])]) }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                                        <div class="h-full rounded-full bg-primary-500/80" style="inline-size: {{ max(6, (int) round($favourite['quantity'] / $most * 100)) }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid min-w-0 grid-cols-1 content-start gap-6 lg:col-span-2">
                {{-- Segments --}}
                <x-filament::section icon="heroicon-o-squares-2x2" :heading="__('crm.guests.profile.segments')">
                    @if ($segments->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('crm.guests.profile.no_segments') }}</p>
                    @else
                        <ul class="grid gap-3">
                            @foreach ($segments as $row)
                                <li class="flex gap-3">
                                    <span @class([
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        'bg-warning-500' => $row->segment->getColor() === 'warning',
                                        'bg-success-500' => $row->segment->getColor() === 'success',
                                        'bg-danger-500' => $row->segment->getColor() === 'danger',
                                        'bg-gray-400' => $row->segment->getColor() === 'gray',
                                    ])></span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $row->product ? __('crm.frequent_of', ['product' => $row->product->name]) : $row->segment->getLabel() }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->segment->description($crm) }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>

                @if ($loyalty)
                    {{-- Rewards --}}
                    <x-filament::section icon="heroicon-o-gift" :heading="__('crm.guests.profile.rewards')">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('crm.guests.profile.wallet') }}</p>
                        @if ($profile['wallet']->isEmpty())
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('crm.guests.profile.no_wallet') }}</p>
                        @else
                            <ul class="mt-2 grid gap-2">
                                @foreach ($profile['wallet'] as $item)
                                    <li class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                                        <span class="truncate text-gray-800 dark:text-gray-100">{{ $item->reward->name }}</span>
                                        @if ($item->expires_at)
                                            <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400" dir="ltr">{{ $date($item->expires_at) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($profile['stamps']->isNotEmpty())
                            <p class="mt-5 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('crm.guests.profile.stamps') }}</p>
                            <ul class="mt-2 grid gap-3">
                                @foreach ($profile['stamps'] as $card)
                                    <li>
                                        <div class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="truncate text-gray-800 dark:text-gray-100">{{ $card->program->name }}</span>
                                            <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400" dir="ltr">{{ Digits::number($card->stamps) }} / {{ Digits::number($card->program->stamps_needed) }}</span>
                                        </div>
                                        <div class="mt-1.5 flex gap-1" aria-hidden="true">
                                            @for ($i = 1; $i <= $card->program->stamps_needed; $i++)
                                                <span @class(['h-1.5 flex-1 rounded-full', 'bg-primary-500' => $i <= $card->stamps, 'bg-gray-100 dark:bg-white/5' => $i > $card->stamps])></span>
                                            @endfor
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-filament::section>
                @endif

                {{-- Consent --}}
                <x-filament::section icon="heroicon-o-bell" :heading="__('crm.guests.profile.offers')">
                    @if ($guest->acceptsOffers())
                        <p class="flex gap-2 text-sm text-success-700 dark:text-success-400">
                            <x-filament::icon icon="heroicon-m-check-circle" class="mt-0.5 size-4 shrink-0" />
                            <span>{{ __('crm.guests.profile.consent_yes', [
                                'date' => $date($guest->offers_consent_at),
                                'source' => __('crm.guests.profile.sources.'.($guest->offers_consent_source ?? 'account')),
                            ]) }}</span>
                        </p>
                    @else
                        <p class="flex gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-m-minus-circle" class="mt-0.5 size-4 shrink-0" />
                            <span>
                                {{ __('crm.guests.profile.consent_no') }}
                                @if ($guest->offers_withdrawn_at)
                                    {{ __('crm.guests.profile.consent_withdrawn', ['date' => $date($guest->offers_withdrawn_at)]) }}
                                @endif
                            </span>
                        </p>
                    @endif
                    @if ($profile['campaigns']['received'] > 0)
                        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 dark:border-white/5">
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('campaigns.guest.received') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number($profile['campaigns']['received']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('campaigns.guest.attributed') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ Digits::number($profile['campaigns']['orders']) }}@if ($profile['campaigns']['orders'] > 0) <span class="font-normal text-gray-400 dark:text-gray-500">· {{ Money::format($profile['campaigns']['revenue_minor'], $currency) }}</span>@endif</dd>
                            </div>
                        </dl>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
