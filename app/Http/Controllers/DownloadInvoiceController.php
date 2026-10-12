<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\InvoicePdf;
use App\Domain\Billing\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class DownloadInvoiceController
{
    public function __invoke(Invoice $invoice, InvoicePdf $pdf): Response
    {
        Gate::authorize('download', $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
