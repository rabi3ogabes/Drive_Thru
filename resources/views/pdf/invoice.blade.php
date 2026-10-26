@php
    use App\Domain\Billing\Enums\InvoiceStatus;
    use App\Support\Money;
    /** @var \App\Domain\Billing\Models\Invoice $invoice */
    $tz = $restaurant->timezone ?? config('app.display_timezone');
    $fmt = fn (int $minor) => Money::format($minor, $invoice->currency, 'en');
    $status = $invoice->status;
@endphp
<html>
<head>
<style>
    body { font-family: dejavusans; color: #111827; font-size: 9.5pt; }
    .ar { font-family: xbriyaz; direction: rtl; text-align: right; }
    .muted { color: #636d82; }
    .brand { font-size: 17pt; font-weight: bold; color: #0f3b3a; }
    .brass { color: #b08d57; }
    .title { font-size: 22pt; color: #0a0f1c; letter-spacing: 1px; }
    .pill { padding: 3px 10px; border-radius: 10px; font-size: 8.5pt; font-weight: bold; }
    .pill-open { background: #fdf3e1; color: #92580b; }
    .pill-paid { background: #e3f5ea; color: #166534; }
    .pill-void { background: #eceff4; color: #475167; }
    table { border-collapse: collapse; width: 100%; }
    .box { background: #f7f5f0; border-radius: 6px; padding: 10px 12px; }
    .lines th { background: #0f3b3a; color: #ffffff; font-size: 8.5pt; padding: 8px 10px; text-align: left; }
    .lines td { padding: 10px; border-bottom: 1px solid #e2dccf; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .totals td { padding: 5px 10px; }
    .grand td { font-size: 12pt; font-weight: bold; color: #0f3b3a; border-top: 2px solid #0f3b3a; padding-top: 8px; }
    .foot { font-size: 8pt; color: #636d82; }
</style>
</head>
<body>

<table>
    <tr>
        <td style="width: 55%;">
            <div class="brand">Drive-Thru<span class="brass">+</span></div>
            <div class="muted">{{ $issuer['name'] }}</div>
            @if ($issuer['address'])<div class="muted">{{ $issuer['address'] }}</div>@endif
            @if ($issuer['tax_number'])<div class="muted">Tax no. · {{ $issuer['tax_number'] }}</div>@endif
            @if ($issuer['email'])<div class="muted">{{ $issuer['email'] }}</div>@endif
        </td>
        <td style="width: 45%; text-align: right;">
            <div class="title">INVOICE</div>
            <div class="ar" style="font-size: 15pt;">فاتورة</div>
            <div style="margin-top: 6px;"><strong>{{ $invoice->number }}</strong></div>
            <div style="margin-top: 6px;">
                <span class="pill pill-{{ $status->value }}">{{ __('billing.invoice_status.'.$status->value, [], 'en') }} · <span class="ar">{{ __('billing.invoice_status.'.$status->value, [], 'ar') }}</span></span>
            </div>
        </td>
    </tr>
</table>

<table style="margin-top: 22px;">
    <tr>
        <td style="width: 50%; padding-right: 8px; vertical-align: top;">
            <div class="box">
                <div class="muted" style="font-size: 8pt;">BILLED TO · <span class="ar">فاتورة إلى</span></div>
                <div style="font-weight: bold; margin-top: 4px;">{{ $restaurant->legal_name ?: $restaurant->getTranslation('name', 'en') }}</div>
                <div class="ar">{{ $restaurant->getTranslation('name', 'ar') }}</div>
                @if ($restaurant->billing_address)<div class="muted">{{ $restaurant->billing_address }}</div>@endif
                @if ($restaurant->tax_number)<div class="muted">Tax no. · {{ $restaurant->tax_number }}</div>@endif
                @if ($restaurant->contact_email)<div class="muted">{{ $restaurant->contact_email }}</div>@endif
            </div>
        </td>
        <td style="width: 50%; padding-left: 8px; vertical-align: top;">
            <div class="box">
                <table>
                    <tr><td class="muted">Issued · <span class="ar">تاريخ الإصدار</span></td><td class="num">{{ $invoice->issued_at->timezone($tz)->format('Y-m-d') }}</td></tr>
                    <tr><td class="muted">Due · <span class="ar">تاريخ الاستحقاق</span></td><td class="num">{{ $invoice->due_at->timezone($tz)->format('Y-m-d') }}</td></tr>
                    @if ($invoice->period_start && $invoice->period_end)
                        <tr><td class="muted">Period · <span class="ar">الفترة</span></td><td class="num">{{ $invoice->period_start->format('Y-m-d') }} → {{ $invoice->period_end->format('Y-m-d') }}</td></tr>
                    @endif
                    @if ($invoice->paid_at)
                        <tr><td class="muted">Paid · <span class="ar">تاريخ الدفع</span></td><td class="num">{{ $invoice->paid_at->timezone($tz)->format('Y-m-d') }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="lines" style="margin-top: 22px;">
    <thead>
        <tr>
            <th style="width: 52%;">Description · <span class="ar">الوصف</span></th>
            <th class="num" style="width: 12%;">Qty · <span class="ar">العدد</span></th>
            <th class="num" style="width: 18%;">Unit · <span class="ar">السعر</span></th>
            <th class="num" style="width: 18%;">Amount · <span class="ar">المبلغ</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>
                    <div>{{ $line->getTranslation('description', 'en') }}</div>
                    <div class="ar muted">{{ $line->getTranslation('description', 'ar') }}</div>
                </td>
                @if ($line->is_discount)
                    <td class="num"></td>
                    <td class="num"></td>
                    <td class="num">−{{ $fmt($line->amount_minor) }}</td>
                @else
                    <td class="num">{{ $line->quantity }}</td>
                    <td class="num">{{ $fmt($line->unit_price_minor) }}</td>
                    <td class="num">{{ $fmt($line->amount_minor) }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 10px; width: 55%;" align="right">
    <tr><td class="muted">Subtotal · <span class="ar">المجموع الفرعي</span></td><td class="num">{{ $fmt($invoice->subtotal_minor) }}</td></tr>
    <tr><td class="muted">Tax ({{ rtrim(rtrim(number_format($invoice->tax_rate_bp / 100, 2, '.', ''), '0'), '.') }}%) · <span class="ar">الضريبة</span></td><td class="num">{{ $fmt($invoice->tax_minor) }}</td></tr>
    <tr class="grand"><td>Total · <span class="ar">الإجمالي</span></td><td class="num">{{ $fmt($invoice->total_minor) }}</td></tr>
</table>

<div style="clear: both;"></div>

@if ($issuer['bank'] && $status === InvoiceStatus::Open)
    <div class="box" style="margin-top: 26px;">
        <div class="muted" style="font-size: 8pt;">PAYMENT DETAILS · <span class="ar">بيانات الدفع</span></div>
        <div style="margin-top: 4px;">{!! nl2br(e($issuer['bank'])) !!}</div>
        <div class="muted" style="margin-top: 4px;">Please use {{ $invoice->number }} as the payment reference.</div>
    </div>
@endif

<div class="foot" style="margin-top: 30px; text-align: center;">
    Thank you for running your drive-thru with {{ config('app.name') }}. · <span class="ar">شكرًا لاختيارك {{ config('app.name') }}.</span>
</div>

</body>
</html>
