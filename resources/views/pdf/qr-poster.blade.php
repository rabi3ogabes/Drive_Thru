@php
    $z = $zoom ?? 1;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans; color: #0b1220; }
        .frame { border: {{ round(0.6 * $z, 2) }}mm solid #0f3b3a; border-radius: {{ round(6 * $z, 2) }}mm; padding: {{ round(9 * $z, 2) }}mm {{ round(8 * $z, 2) }}mm {{ round(7 * $z, 2) }}mm; text-align: center; }
        .eyebrow { font-size: {{ round(7.5 * $z, 2) }}pt; color: #8a6a3f; }
        .eyebrow .latin { letter-spacing: {{ round(1.5 * $z, 2) }}pt; text-transform: uppercase; }
        .eyebrow .arabic { font-family: xbriyaz; font-size: {{ round(9 * $z, 2) }}pt; letter-spacing: 0; }
        .name-ar { font-family: xbriyaz; font-size: {{ round(24 * $z, 2) }}pt; color: #0f3b3a; margin-top: {{ round(3 * $z, 2) }}mm; }
        .name-en { font-size: {{ round(17 * $z, 2) }}pt; color: #0f3b3a; margin-top: {{ round(1 * $z, 2) }}mm; }
        .branch { font-size: {{ round(10.5 * $z, 2) }}pt; color: #4b5563; margin-top: {{ round(2 * $z, 2) }}mm; }
        .qr { margin: {{ round(6 * $z, 2) }}mm auto {{ round(4 * $z, 2) }}mm; padding: {{ round(3 * $z, 2) }}mm; border-radius: {{ round(4 * $z, 2) }}mm; background: #f7f5f0; width: {{ round(88 * $z, 2) }}mm; }
        .cta-ar { font-family: xbriyaz; font-size: {{ round(15 * $z, 2) }}pt; color: #0b1220; }
        .cta-en { font-size: {{ round(11 * $z, 2) }}pt; color: #0b1220; margin-top: {{ round(0.5 * $z, 2) }}mm; }
        .url { font-size: {{ round(9.5 * $z, 2) }}pt; color: #0f3b3a; margin-top: {{ round(3 * $z, 2) }}mm; }
        .foot { font-size: {{ round(6.5 * $z, 2) }}pt; color: #9ca3af; margin-top: {{ round(5 * $z, 2) }}mm; }
    </style>
</head>
<body>
    <div class="frame">
        @if (($kind ?? 'order') === 'stamps')
            <div class="eyebrow"><span class="latin">Stamp card</span> · <span class="arabic" dir="rtl">بطاقة الأختام</span></div>
        @else
            <div class="eyebrow"><span class="latin">Drive-thru</span> · <span class="arabic" dir="rtl">درايف ثرو</span></div>
        @endif
        <div class="name-ar" dir="rtl">{{ $restaurant->getTranslation('name', 'ar') }}</div>
        <div class="name-en">{{ $restaurant->getTranslation('name', 'en') }}</div>
        @if ($branch)
            <div class="branch"><span dir="rtl">{{ $branch->getTranslation('name', 'ar') }}</span> · {{ $branch->getTranslation('name', 'en') }}</div>
        @endif

        <div class="qr"><img src="{{ $qr }}" width="{{ round(82 * $z, 2) }}mm" height="{{ round(82 * $z, 2) }}mm"></div>

        @php
            $cta = ($kind ?? 'order') === 'stamps' ? 'links.poster.stamps_cta' : 'links.poster.cta';
        @endphp
        <div class="cta-ar" dir="rtl">{{ __($cta, [], 'ar') }}</div>
        <div class="cta-en">{{ __($cta, [], 'en') }}</div>
        <div class="url">{{ $url }}</div>
        <div class="foot"><img src="{{ \App\Support\Brand::markDataUri() }}" width="{{ round(4 * $z, 2) }}mm" height="{{ round(4 * $z, 2) }}mm" style="vertical-align: middle;"> {{ \App\Support\Brand::full() }}</div>
    </div>
</body>
</html>
