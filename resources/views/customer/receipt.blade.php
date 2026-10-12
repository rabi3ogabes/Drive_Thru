@php
    use App\Support\Money;
    $tz = $restaurant->timezone;
    $currency = $order->currency;
@endphp
<x-layouts.customer :restaurant="$restaurant" :title="__('guest.receipt.title', ['number' => $order->displayNumber()])">
    <main id="main" class="mx-auto max-w-md px-4 py-8 print:max-w-none print:p-0">
        <article class="rounded-[2rem] bg-white px-6 py-7 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5 print:rounded-none print:shadow-none print:ring-0">
            <header class="text-center">
                <x-public.restaurant-logo :restaurant="$restaurant" class="mx-auto size-12" />
                <h1 class="mt-3 text-lg font-semibold text-ink-950">{{ $restaurant->legal_name ?: $restaurant->name }}</h1>
                <p class="text-sm text-ink-500">{{ $order->branch->name }}</p>
                @if ($restaurant->tax_number)<p class="text-xs text-ink-400">{{ __('guest.receipt.tax_number') }} <span dir="ltr">{{ $restaurant->tax_number }}</span></p>@endif
            </header>

            <dl class="mt-6 grid grid-cols-2 gap-y-1 border-y border-dashed border-ink-950/15 py-3 text-sm">
                <dt class="text-ink-500">{{ __('guest.receipt.order') }}</dt><dd class="text-end font-semibold tabular-nums">{{ $order->displayNumber() }}</dd>
                <dt class="text-ink-500">{{ __('guest.receipt.date') }}</dt><dd class="text-end tabular-nums" dir="ltr">{{ $order->placed_at?->setTimezone($tz)->format('Y-m-d H:i') }}</dd>
                <dt class="text-ink-500">{{ __('guest.receipt.payment') }}</dt><dd class="text-end">{{ $order->payment_method->getLabel() }}@if ($order->payment_method->isOnline()) · {{ $order->payment_status->getLabel() }}@endif</dd>
            </dl>

            <ul class="mt-4 space-y-2.5 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3">
                        <div>
                            <p class="font-medium text-ink-900"><span class="tabular-nums">{{ $item->quantity }}×</span> {{ $item->localName() }}</p>
                            @if ($labels = $item->optionLabels())<p class="text-xs text-ink-500">{{ implode('، ', $labels) }}</p>@endif
                        </div>
                        <p class="shrink-0 tabular-nums" dir="ltr">{{ Money::format($item->line_total_minor, $currency) }}</p>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-4 space-y-1 border-t border-dashed border-ink-950/15 pt-3 text-sm tabular-nums">
                <div class="flex justify-between text-ink-600"><dt>{{ __('guest.checkout.subtotal') }}</dt><dd dir="ltr">{{ Money::format($order->subtotal_minor, $currency) }}</dd></div>
                @foreach ($order->adjustments as $adjustment)
                    @continue($adjustment->amount_minor === 0)
                    <div class="flex justify-between gap-3 text-ink-600"><dt>{{ $adjustment->localLabel() }}</dt><dd dir="ltr">−{{ Money::format($adjustment->amount_minor, $currency) }}</dd></div>
                @endforeach
                @if ($order->service_fee_minor > 0)<div class="flex justify-between text-ink-600"><dt>{{ __('guest.checkout.service_fee') }}</dt><dd dir="ltr">{{ Money::format($order->service_fee_minor, $currency) }}</dd></div>@endif
                @if ($order->tax_minor > 0)<div class="flex justify-between text-ink-600"><dt>{{ __('guest.checkout.tax') }}</dt><dd dir="ltr">{{ Money::format($order->tax_minor, $currency) }}</dd></div>@endif
                <div class="flex justify-between pt-1 text-base font-semibold text-ink-950"><dt>{{ __('guest.checkout.total') }}</dt><dd dir="ltr">{{ Money::format($order->total_minor, $currency) }}</dd></div>
            </dl>

            @if ($order->points_earned > 0 || $order->points_redeemed > 0)
                <p class="mt-3 text-center text-xs text-ink-500">
                    @if ($order->points_redeemed > 0){{ __('guest.receipt.points_used', ['points' => number_format($order->points_redeemed)]) }}@endif
                    @if ($order->points_redeemed > 0 && $order->points_earned > 0) · @endif
                    @if ($order->points_earned > 0){{ __('guest.order.points_earned', ['points' => number_format($order->points_earned)]) }}@endif
                </p>
            @endif
            <p class="mt-6 text-center text-xs text-ink-400">{{ __('guest.receipt.thanks') }}</p>
        </article>

        <div class="mt-5 flex gap-2 print:hidden">
            <a href="{{ route('customer.order', [$restaurant, $order]) }}" class="flex-1 rounded-full bg-white py-3 text-center text-sm font-semibold text-ink-800 ring-1 ring-ink-950/10">{{ __('guest.receipt.back') }}</a>
            <button type="button" onclick="window.print()" class="flex-1 rounded-full bg-ink-950 py-3 text-sm font-semibold text-white">{{ __('guest.receipt.print') }}</button>
        </div>
    </main>
</x-layouts.customer>
