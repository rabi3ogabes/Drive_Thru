<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Records a payment. When it clears the last overdue invoice of a past-due or
 * billing-suspended client, the client is reactivated automatically.
 */
final class MarkInvoicePaid
{
    public function handle(Invoice $invoice, string $method, ?string $reference = null, ?CarbonImmutable $paidAt = null): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Open) {
            throw new DomainException(__('billing.errors.invoice_not_open'));
        }

        DB::transaction(function () use ($invoice, $method, $reference, $paidAt): void {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $paidAt ?? CarbonImmutable::now(),
                'payment_method' => $method,
                'payment_reference' => $reference,
            ]);

            $subscription = $invoice->subscription;

            if ($subscription === null || ! in_array($subscription->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended], true)) {
                return;
            }

            $stillOverdue = Invoice::withoutTenancy()
                ->where('subscription_id', $subscription->id)
                ->overdue()
                ->exists();

            if ($stillOverdue) {
                return;
            }

            $restaurant = $invoice->restaurant()->firstOrFail();

            // Only lift suspensions caused by billing; a manual suspension stays until lifted by hand.
            if ($subscription->status === SubscriptionStatus::Suspended && $restaurant->suspension_reason !== SuspendRestaurant::BILLING_REASON) {
                return;
            }

            $subscription->update(['status' => SubscriptionStatus::Active, 'grace_ends_at' => null]);
            $restaurant->forceFill([
                'status' => RestaurantStatus::Active,
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();
        });

        return $invoice->refresh();
    }
}
