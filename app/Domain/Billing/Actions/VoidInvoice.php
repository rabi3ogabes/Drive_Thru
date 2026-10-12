<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use DomainException;

/**
 * Cancels an unpaid invoice. Paid invoices are never voided (refunds are a
 * separate flow), and the number is kept so the sequence stays gap-free.
 */
final class VoidInvoice
{
    public function handle(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Open) {
            throw new DomainException(__('billing.errors.invoice_not_open'));
        }

        $invoice->update([
            'status' => InvoiceStatus::Void,
            'voided_at' => now(),
            'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '').$reason),
        ]);

        return $invoice;
    }
}
