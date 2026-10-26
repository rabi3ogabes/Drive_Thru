<?php

declare(strict_types=1);

namespace App\Domain\Printing;

use App\Domain\Ordering\Models\Order;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * 80 mm kitchen tickets and receipts. The same Blade view serves the browser
 * (print dialog) and PrintNode (as a PDF), so both always match.
 */
final class TicketRenderer
{
    public const KITCHEN = 'kitchen';

    public const RECEIPT = 'receipt';

    public function html(Order $order, string $kind, bool $forPdf = false): string
    {
        $order->loadMissing(['items', 'branch.restaurant', 'lane', 'customer', 'payment', 'adjustments']);

        return view('print.ticket', ['order' => $order, 'kind' => $kind, 'forPdf' => $forPdf])->render();
    }

    public function pdf(Order $order, string $kind): string
    {
        $tempDir = storage_path('framework/cache/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        // A tall page for a continuous roll: printers cut at the end of the content.
        $height = 120 + 14 * $order->items()->count() + ($kind === self::RECEIPT ? 60 : 0);

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [80, $height],
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 4,
            'margin_bottom' => 4,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);

        $pdf->SetTitle($order->displayNumber());
        $pdf->WriteHTML($this->html($order, $kind, forPdf: true));

        return $pdf->Output($order->displayNumber().'.pdf', Destination::STRING_RETURN);
    }
}
