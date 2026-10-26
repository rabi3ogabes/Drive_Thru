<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Notifications\InvoiceIssued;
use App\Domain\Billing\SubscriptionCharges;
use App\Domain\Platform\PlatformSettings;
use App\Support\Money;
use App\Support\Sequence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bills one subscription period: numbered invoice (DTP-2026-00001), a line
 * per service (and the plan tier's fee, if any), the bundle discount,
 * platform tax, due date, and an email to the owner. Billing the same period
 * twice returns the existing invoice.
 */
final class IssueInvoice
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly SubscriptionCharges $charges,
    ) {}

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

        return $this->create($subscription, $this->charges->lines($subscription), $periodStart, $periodEnd);
    }

    /**
     * A one-off invoice outside the regular cycle (a service added mid-period).
     *
     * @param  list<array{description: array{ar: string, en: string}, quantity: int, unit_price_minor: int, amount_minor: int, is_discount: bool}>  $lines
     */
    public function oneOff(Subscription $subscription, array $lines, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Invoice
    {
        return $this->create($subscription, $lines, $periodStart, $periodEnd);
    }

    /**
     * @param  list<array{description: array{ar: string, en: string}, quantity: int, unit_price_minor: int, amount_minor: int, is_discount: bool}>  $lines
     */
    private function create(Subscription $subscription, array $lines, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Invoice
    {
        $invoice = DB::transaction(function () use ($subscription, $lines, $periodStart, $periodEnd): Invoice {
            $now = CarbonImmutable::now();

            $subtotal = SubscriptionCharges::sum($lines);
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

            foreach ($lines as $sort => $line) {
                $invoice->lines()->create([...$line, 'sort' => $sort]);
            }

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
