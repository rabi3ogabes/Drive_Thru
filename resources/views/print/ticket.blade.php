@php
    use App\Support\Locales;
    use App\Support\Money;
    $restaurant = $order->branch->restaurant;
    $tz = $restaurant->timezone;
    $locale = $kind === 'receipt' ? $order->locale : app()->getLocale();
    $previous = app()->getLocale();
    app()->setLocale($locale);
    $dir = Locales::direction($locale);
    $currency = $order->currency;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ $order->displayNumber() }} · {{ __('print.'.$kind) }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: {{ $forPdf ? 'dejavusans' : 'system-ui, -apple-system, "Segoe UI", "Noto Sans Arabic", sans-serif' }}; font-size: 11pt; color: #000; width: {{ $forPdf ? '100%' : '74mm' }}; }
        .center { text-align: center; }
        .muted { color: #333; font-size: 9pt; }
        .big { font-size: 26pt; font-weight: bold; letter-spacing: 1px; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        .qty { width: 9mm; font-weight: bold; font-size: 13pt; }
        .amount { text-align: {{ $dir === 'rtl' ? 'left' : 'right' }}; white-space: nowrap; direction: ltr; }
        .item { font-size: {{ $kind === 'kitchen' ? '13pt' : '11pt' }}; font-weight: bold; }
        .opt { font-size: 10pt; }
        .note { font-size: 11pt; font-weight: bold; border: 1px solid #000; padding: 2px 4px; margin-top: 2px; }
        .total td { font-size: 13pt; font-weight: bold; padding-top: 4px; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        @media screen { body { margin: 16px auto; } .noprint { display: block; margin: 16px auto; } }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="center">
        <div><strong>{{ $kind === 'receipt' ? ($restaurant->legal_name ?: $restaurant->name) : $restaurant->name }}</strong></div>
        <div class="muted">{{ $order->branch->name }}</div>
        @if ($kind === 'receipt' && $restaurant->tax_number)
            <div class="muted">{{ __('guest.receipt.tax_number') }} <span class="ltr">{{ $restaurant->tax_number }}</span></div>
        @endif
        <div class="rule"></div>
        <div class="muted">{{ __('print.'.$kind) }}</div>
        <div class="big ltr">{{ $order->displayNumber() }}</div>
        <div class="muted ltr">{{ $order->placed_at?->setTimezone($tz)->format('Y-m-d H:i') }} · {{ $order->short_code }}</div>
    </div>

    @if ($kind === 'kitchen')
        <div class="rule"></div>
        @if ($order->scheduled_for)
            <div><strong>{{ __('print.for_time', ['time' => $order->scheduled_for->setTimezone($tz)->format('H:i')]) }}</strong></div>
        @elseif ($order->timing->value === 'on_the_way')
            <div><strong>{{ __('print.on_the_way') }}</strong></div>
        @endif
        @if ($order->lane)<div>{{ __('print.lane') }}: {{ $order->lane->name }}</div>@endif
        @if ($order->vehicle)<div>{{ __('print.car') }}: <bdi>{{ $order->vehicle['label'] ?? $order->vehicle['plate'] ?? '' }}</bdi></div>@endif
        <div>{{ __('print.guest') }}: {{ $order->customer?->firstName() ?? '—' }}</div>
    @endif

    <div class="rule"></div>
    <table>
        @foreach ($order->items as $item)
            <tr>
                <td class="qty ltr">{{ $item->quantity }}×</td>
                <td>
                    <div class="item">{{ $item->localName($locale) }}</div>
                    @if ($labels = $item->optionLabels($locale))<div class="opt">{{ implode(' · ', $labels) }}</div>@endif
                    @if ($item->notes)<div class="note">{{ $item->notes }}</div>@endif
                </td>
                @if ($kind === 'receipt')<td class="amount">{{ Money::format($item->line_total_minor, $currency) }}</td>@endif
            </tr>
        @endforeach
    </table>

    @if ($order->notes)
        <div class="note">{{ __('print.order_note') }}: {{ $order->notes }}</div>
    @endif

    @if ($kind === 'receipt')
        <div class="rule"></div>
        <table>
            <tr><td>{{ __('guest.checkout.subtotal') }}</td><td class="amount">{{ Money::format($order->subtotal_minor, $currency) }}</td></tr>
            @if ($order->discount_minor > 0)<tr><td>{{ __('guest.checkout.discount') }}</td><td class="amount">−{{ Money::format($order->discount_minor, $currency) }}</td></tr>@endif
            @if ($order->service_fee_minor > 0)<tr><td>{{ __('guest.checkout.service_fee') }}</td><td class="amount">{{ Money::format($order->service_fee_minor, $currency) }}</td></tr>@endif
            @if ($order->tax_minor > 0)<tr><td>{{ __('guest.checkout.tax') }}</td><td class="amount">{{ Money::format($order->tax_minor, $currency) }}</td></tr>@endif
            <tr class="total"><td>{{ __('guest.checkout.total') }}</td><td class="amount">{{ Money::format($order->total_minor, $currency) }}</td></tr>
        </table>
        <div class="muted">{{ $order->payment_method->getLabel() }} · {{ $order->staffPaymentLabel() }}</div>
        <div class="rule"></div>
        <div class="center">{{ __('guest.receipt.thanks') }}</div>
    @endif

    @unless ($forPdf)
        <button type="button" class="noprint" onclick="window.print()">{{ __('print.print_again') }}</button>
        <script>window.addEventListener('load', () => window.print());</script>
    @endunless
</body>
</html>
@php app()->setLocale($previous); @endphp
