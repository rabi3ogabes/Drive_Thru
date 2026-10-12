<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Platform\PlatformSettings;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders a bilingual (English / Arabic) invoice PDF with mPDF, which shapes
 * Arabic correctly without a headless browser.
 */
final class InvoicePdf
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'subscription.plan']);
        $restaurant = $invoice->restaurant()->firstOrFail();

        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);

        $pdf->SetTitle($invoice->number);
        $pdf->SetAuthor((string) $this->settings->string('billing.legal_name'));
        $pdf->SetCreator(config('app.name'));

        $pdf->WriteHTML(view('pdf.invoice', [
            'invoice' => $invoice,
            'restaurant' => $restaurant,
            'issuer' => [
                'name' => $this->settings->string('billing.legal_name') ?? config('app.name'),
                'address' => $this->settings->string('billing.address'),
                'tax_number' => $this->settings->string('billing.tax_number'),
                'email' => $this->settings->string('billing.email'),
                'bank' => $this->settings->string('billing.bank_details'),
            ],
        ])->render());

        return $pdf->Output($invoice->number.'.pdf', Destination::STRING_RETURN);
    }
}
