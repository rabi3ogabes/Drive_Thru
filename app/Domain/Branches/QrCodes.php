<?php

declare(strict_types=1);

namespace App\Domain\Branches;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use App\Support\Brand;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * QR codes for restaurant and branch links: crisp SVG for designers, a large
 * PNG for quick sharing, and a print-ready bilingual A5 poster (PDF).
 * Generated locally; no third-party QR service ever sees the links.
 */
final class QrCodes
{
    public function svg(string $url): string
    {
        return (new QRCode($this->options(QROutputInterface::MARKUP_SVG)))->render($url);
    }

    public function png(string $url): string
    {
        return (new QRCode($this->options(QROutputInterface::GDIMAGE_PNG, scale: 20)))->render($url);
    }

    /** Print sizes: the A5 poster, the same design on A4, and a 5 × 5 cm sticker. */
    public const FORMATS = ['a5', 'a4', 'sticker'];

    /**
     * @param  'order'|'stamps'  $kind  what the guest does after scanning
     * @param  'a5'|'a4'|'sticker'  $format
     */
    public function poster(Restaurant $restaurant, ?Branch $branch, string $url, string $kind = 'order', string $format = 'a5'): string
    {
        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $sticker = $format === 'sticker';
        $margin = match ($format) {
            'a4' => [17, 17, 20, 17],
            'sticker' => [3, 3, 3, 2],
            default => [12, 12, 14, 12],
        };

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => match ($format) {
                'a4' => 'A4',
                'sticker' => [50, 50],
                default => 'A5',
            },
            'margin_left' => $margin[0],
            'margin_right' => $margin[1],
            'margin_top' => $margin[2],
            'margin_bottom' => $margin[3],
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);

        $pdf->SetTitle($restaurant->getTranslation('name', 'en').($branch !== null ? ' · '.$branch->getTranslation('name', 'en') : ''));
        $pdf->SetCreator(Brand::latin());

        $pdf->WriteHTML(view($sticker ? 'pdf.qr-sticker' : 'pdf.qr-poster', [
            'restaurant' => $restaurant,
            'branch' => $branch,
            'url' => Links::display($url),
            'qr' => 'data:image/png;base64,'.base64_encode($this->png($url)),
            'kind' => $kind,
            'zoom' => $format === 'a4' ? 1.41 : 1,
        ])->render());

        return $pdf->Output('qr.pdf', Destination::STRING_RETURN);
    }

    private function options(string $type, int $scale = 10): QROptions
    {
        return new QROptions([
            'outputType' => $type,
            'outputBase64' => false,
            // Medium correction survives a little wear on printed stickers.
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'svgAddXmlHeader' => false,
        ]);
    }
}
