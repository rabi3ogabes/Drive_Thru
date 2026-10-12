<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans; color: #0b1220; }
        .frame { border: 0.6mm solid #0f3b3a; border-radius: 6mm; padding: 9mm 8mm 7mm; text-align: center; }
        .eyebrow { font-size: 7.5pt; color: #8a6a3f; }
        .eyebrow .latin { letter-spacing: 1.5pt; text-transform: uppercase; }
        .eyebrow .arabic { font-family: xbriyaz; font-size: 9pt; letter-spacing: 0; }
        .name-ar { font-family: xbriyaz; font-size: 24pt; color: #0f3b3a; margin-top: 3mm; }
        .name-en { font-size: 17pt; color: #0f3b3a; margin-top: 1mm; }
        .branch { font-size: 10.5pt; color: #4b5563; margin-top: 2mm; }
        .qr { margin: 6mm auto 4mm; padding: 3mm; border-radius: 4mm; background: #f7f5f0; width: 88mm; }
        .cta-ar { font-family: xbriyaz; font-size: 15pt; color: #0b1220; }
        .cta-en { font-size: 11pt; color: #0b1220; margin-top: 0.5mm; }
        .url { font-size: 9.5pt; color: #0f3b3a; margin-top: 3mm; }
        .foot { font-size: 6.5pt; color: #9ca3af; margin-top: 5mm; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="eyebrow"><span class="latin">Drive-thru</span> · <span class="arabic" dir="rtl">درايف ثرو</span></div>
        <div class="name-ar" dir="rtl">{{ $restaurant->getTranslation('name', 'ar') }}</div>
        <div class="name-en">{{ $restaurant->getTranslation('name', 'en') }}</div>
        @if ($branch)
            <div class="branch"><span dir="rtl">{{ $branch->getTranslation('name', 'ar') }}</span> · {{ $branch->getTranslation('name', 'en') }}</div>
        @endif

        <div class="qr"><img src="{{ $qr }}" width="82mm" height="82mm"></div>

        <div class="cta-ar" dir="rtl">{{ __('links.poster.cta', [], 'ar') }}</div>
        <div class="cta-en">{{ __('links.poster.cta', [], 'en') }}</div>
        <div class="url">{{ $url }}</div>
        <div class="foot">{{ config('app.name') }}</div>
    </div>
</body>
</html>
