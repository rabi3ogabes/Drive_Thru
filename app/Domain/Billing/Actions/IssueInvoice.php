<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Notifications\InvoiceIssued;
use App\Domain\Platform\PlatformSettings;
use App\Support\Money;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bills one subscription period: numbered invoice (DTP-2026-00001), one line
 * per billed branch, platform tax, due date, and an email to the owner.
 * Billing the same period twice returns the existing invoice.
 */
final class IssueInvoice
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function handle(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Invoice
    {
        $existing = Invoice::withoutTenancy()
            ->where('subscription_id', $subscription->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->where('status', '!=', InvoiceStatus::Void)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $invoice = DB::transaction(function () use ($subscription, $periodStart, $periodEnd): Invoice {
            $subscription->loadMissing('plan');
            $now = CarbonImmutable::now();

            $subtotal = $subscription->amountPerPeriodMinor();
            $taxRate = $this->settings->int('billing.tax_rate_bp');
            $tax = Money::percentOf($subtotal, $taxRate);

            $invoice = Invoice::create([
                'restaurant_id' => $subscription->restaurant_id,
                'subscription_id' => $subscription->id,
                'number' => $this->nextNumber($now),
                'status' => InvoiceStatus::Open,
                'currency' => $subscription->currency,
                'subtotal_minor' => $subtotal,
                'tax_rate_bp' => $taxRate,
                'tax_minor' => $tax,
                'total_minor' => $subtotal + $tax,
                'period_start' => $periodStart->startOfDay(),
                'period_end' => $periodEnd->startOfDay(),
                'issued_at' => $now,
                'due_at' => $now->addDays($this->settings->int('billing.invoice_due_days'))->endOfDay(),
            ]);

            $invoice->lines()->create([
                'description' => [
                    'ar' => __('billing.invoice.line', ['plan' => $subscription->plan->getTranslation('name', 'ar')], 'ar'),
                    'en' => __('billing.invoice.line', ['plan' => $subscription->plan->getTranslation('name', 'en')], 'en'),
                ],
                'quantity' => $subscription->quantity,
                'unit_price_minor' => $subscription->unit_price_minor,
                'amount_minor' => $subtotal,
            ]);

            return $invoice;
        });

        $owner = $invoice->restaurant()->first()?->owner();
        $owner?->notify(new InvoiceIssued($invoice));

        return $invoice;
    }

    private function nextNumber(CarbonImmutable $now): string
    {
        $year = $now->year;

        return sprintf('DTP-%d-%05d', $year, Sequence::next("invoice-{$year}"));
    }
}
