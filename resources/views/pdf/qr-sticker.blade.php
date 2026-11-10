{{-- A 5 × 5 cm sticker (car windows, tables, packaging): the code, a short bilingual line and the link. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        {{-- Everything fits one 50 mm page: 31 mm of code, then three tight lines. --}}
        body { font-family: dejavusans; color: #0b1220; text-align: center; line-height: 1.15; }
        .qr { margin: 0 auto; width: 31mm; }
        .cta-ar { font-family: xbriyaz; font-size: 7pt; color: #0f3b3a; margin-top: 0.4mm; }
        .cta-en { font-size: 5.5pt; color: #0f3b3a; margin-top: 0.6mm; }
        .url { font-size: 4.5pt; color: #6b7280; margin-top: 0.3mm; }
    </style>
</head>
<body>
    <div class="qr"><img src="{{ $qr }}" width="31mm" height="31mm"></div>
    <div class="cta-ar" dir="rtl">{{ __('links.poster.cta', [], 'ar') }}</div>
    <div class="cta-en">{{ __('links.poster.cta', [], 'en') }}</div>
    <div class="url">{{ $url }} · {{ \App\Support\Brand::full() }}</div>
</body>
</html>
