<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Notifications\PaymentOverdue;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use Carbon\CarbonImmutable;

/**
 * The daily billing run. Idempotent: running it twice on the same day has no
 * extra effect. In order:
 *  1. trials that ended become active and get their first invoice,
 *  2. periods that ended roll over and get invoiced,
 *  3. clients with an overdue invoice become past due (grace period starts),
 *  4. clients still unpaid after the grace period are suspended.
 */
final class RunBillingCycle
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly SuspendRestaurant $suspend,
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * @return array{trials_converted: int, periods_renewed: int, marked_past_due: int, suspended: int}
     */
    public function handle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $report = ['trials_converted' => 0, 'periods_renewed' => 0, 'marked_past_due' => 0, 'suspended' => 0];

        Subscription::withoutTenancy()->with(['plan', 'restaurant'])
            ->where('status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<=', $now)
            ->each(function (Subscription $subscription) use (&$report): void {
                $start = $subscription->trial_ends_at ?? CarbonImmutable::now();
                $end = $subscription->interval->addTo($start);

                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => $start,
                    'current_period_end' => $end,
                ]);
                $subscription->restaurant?->forceFill(['status' => RestaurantStatus::Active])->save();

                if ($subscription->amountPerPeriodMinor() > 0) {
                    $this->issueInvoice->handle($subscription, $start, $end);
                }

                $report['trials_converted']++;
            });

        Subscription::withoutTenancy()->with('plan')
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->where('current_period_end', '<=', $now)
            ->each(function (Subscription $subscription) use ($now, &$report): void {
                // Catch up every missed period (e.g. the scheduler was down for days).
                while ($subscription->current_period_end !== null && $subscription->current_period_end->lte($now)) {
                    $start = $subscription->current_period_end;
                    $end = $subscription->interval->addTo($start);

                    $subscription->update(['current_period_start' => $start, 'current_period_end' => $end]);

                    if ($subscription->amountPerPeriodMinor() > 0) {
                        $this->issueInvoice->handle($subscription, $start, $end);
                    }

                    $report['periods_renewed']++;
                }
            });

        $graceDays = $this->settings->int('billing.grace_days');

        Invoice::withoutTenancy()->overdue()
            ->whereHas('subscription', fn ($q) => $q->where('status', SubscriptionStatus::Active))
            ->with('subscription')
            ->each(function (Invoice $invoice) use ($graceDays, &$report): void {
                $subscription = $invoice->subscription;

                if ($subscription === null || $subscription->status !== SubscriptionStatus::Active) {
                    return;
                }

                $subscription->update([
                    'status' => SubscriptionStatus::PastDue,
                    'grace_ends_at' => $invoice->due_at->addDays($graceDays),
                ]);

                $invoice->restaurant()->first()?->owner()?->notify(new PaymentOverdue($invoice, $subscription));

                $report['marked_past_due']++;
            });

        Subscription::withoutTenancy()->with('restaurant')
            ->where('status', SubscriptionStatus::PastDue)
            ->where('grace_ends_at', '<=', $now)
            ->each(function (Subscription $subscription) use (&$report): void {
                $stillUnpaid = Invoice::withoutTenancy()->where('subscription_id', $subscription->id)->overdue()->exists();

                if ($stillUnpaid && $subscription->restaurant !== null) {
                    $this->suspend->handle($subscription->restaurant, SuspendRestaurant::BILLING_REASON);
                    $report['suspended']++;
                }
            });

        return $report;
    }
}
