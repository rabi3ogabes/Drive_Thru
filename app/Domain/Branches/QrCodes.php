<?php

declare(strict_types=1);

namespace App\Domain\Branches;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
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

    public function poster(Restaurant $restaurant, ?Branch $branch, string $url): string
    {
        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A5',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 12,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);

        $pdf->SetTitle($restaurant->getTranslation('name', 'en').($branch !== null ? ' · '.$branch->getTranslation('name', 'en') : ''));
        $pdf->SetCreator(config('app.name'));

        $pdf->WriteHTML(view('pdf.qr-poster', [
            'restaurant' => $restaurant,
            'branch' => $branch,
            'url' => Links::display($url),
            'qr' => 'data:image/png;base64,'.base64_encode($this->png($url)),
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
