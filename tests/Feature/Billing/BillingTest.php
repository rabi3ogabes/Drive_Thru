<?php

use App\Domain\Billing\Actions\IssueInvoice;
use App\Domain\Billing\Actions\MarkInvoicePaid;
use App\Domain\Billing\Actions\RunBillingCycle;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Actions\SuspendRestaurant;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\InvoicePdf;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Notifications\InvoiceIssued;
use App\Domain\Billing\Notifications\PaymentOverdue;
use App\Domain\Billing\Notifications\RestaurantSuspended;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
});

function subscribed(?Plan $plan = null, int $branches = 1, ?int $trialDays = null): array
{
    $restaurant = restaurant(slug: 'masar-'.uniqid());
    $owner = ownerOf($restaurant);
    $plan ??= Plan::factory()->create(['price_minor' => 64900]);

    $subscription = app(StartSubscription::class)->handle($restaurant, $plan, $branches, $trialDays);

    return [$restaurant->fresh(), $subscription, $owner, $plan];
}

it('starts a free trial without billing', function () {
    [$restaurant, $subscription] = subscribed(trialDays: 14);

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at->toDateString())->toBe('2026-10-15')
        ->and($restaurant->status)->toBe(RestaurantStatus::Trial)
        ->and(Invoice::withoutTenancy()->count())->toBe(0);
});

it('bills immediately when there is no trial, per branch', function () {
    [$restaurant, $subscription, $owner] = subscribed(branches: 3, trialDays: 0);

    $invoice = Invoice::withoutTenancy()->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($restaurant->status)->toBe(RestaurantStatus::Active)
        ->and($invoice->number)->toBe('DTP-2026-00001')
        ->and($invoice->subtotal_minor)->toBe(194700)
        ->and($invoice->total_minor)->toBe(194700)
        ->and($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->quantity)->toBe(3)
        ->and($invoice->due_at->toDateString())->toBe('2026-10-08');

    Notification::assertSentTo($owner, InvoiceIssued::class);
});

it('applies the platform tax rate with exact rounding', function () {
    app(PlatformSettings::class)->set(['billing.tax_rate_bp' => 500]);

    subscribed(Plan::factory()->create(['price_minor' => 34900]), trialDays: 0);

    $invoice = Invoice::withoutTenancy()->sole();

    expect($invoice->tax_rate_bp)->toBe(500)
        ->and($invoice->tax_minor)->toBe(1745)
        ->and($invoice->total_minor)->toBe(36645);
});

it('numbers invoices sequentially without gaps', function () {
    subscribed(trialDays: 0);
    subscribed(trialDays: 0);
    subscribed(trialDays: 0);

    expect(Invoice::withoutTenancy()->orderBy('id')->pluck('number')->all())
        ->toBe(['DTP-2026-00001', 'DTP-2026-00002', 'DTP-2026-00003']);
});

it('never bills the same period twice', function () {
    [, $subscription] = subscribed(trialDays: 0);

    $again = app(IssueInvoice::class)->handle($subscription, $subscription->current_period_start, $subscription->current_period_end);

    expect(Invoice::withoutTenancy()->count())->toBe(1)->and($again->number)->toBe('DTP-2026-00001');
});

it('turns an ended trial into a paid period', function () {
    [$restaurant, $subscription] = subscribed(trialDays: 14);

    $this->travelTo(now()->addDays(14)->addHour());
    $report = app(RunBillingCycle::class)->handle();

    expect($report['trials_converted'])->toBe(1)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->current_period_end->toDateString())->toBe('2026-11-15')
        ->and($restaurant->fresh()->status)->toBe(RestaurantStatus::Active)
        ->and(Invoice::withoutTenancy()->count())->toBe(1);
});

it('renews periods and catches up missed ones', function () {
    [, $subscription] = subscribed(trialDays: 0);

    $this->travelTo(now()->addMonths(2)->addDay());
    $report = app(RunBillingCycle::class)->handle();

    expect($report['periods_renewed'])->toBe(2)
        ->and(Invoice::withoutTenancy()->count())->toBe(3)
        ->and($subscription->fresh()->current_period_end->toDateString())->toBe('2027-01-01');
});

it('is safe to run twice on the same day', function () {
    subscribed(trialDays: 0);
    $this->travelTo(now()->addMonth()->addDay());

    app(RunBillingCycle::class)->handle();
    $second = app(RunBillingCycle::class)->handle();

    expect($second)->toBe(['services' => ['reminded' => 0, 'converted' => 0, 'demos_ended' => 0, 'ended' => 0], 'trials_converted' => 0, 'periods_renewed' => 0, 'marked_past_due' => 0, 'suspended' => 0])
        ->and(Invoice::withoutTenancy()->count())->toBe(2);
});

it('marks unpaid clients past due, then suspends them after the grace period', function () {
    [$restaurant, $subscription, $owner] = subscribed(trialDays: 0);

    $this->travelTo(now()->addDays(8));
    app(RunBillingCycle::class)->handle();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->fresh()->grace_ends_at->toDateString())->toBe('2026-10-15')
        ->and($restaurant->fresh()->status)->toBe(RestaurantStatus::Active);
    Notification::assertSentTo($owner, PaymentOverdue::class);

    $this->travelTo(now()->addDays(8));
    app(RunBillingCycle::class)->handle();

    expect($restaurant->fresh()->status)->toBe(RestaurantStatus::Suspended)
        ->and($restaurant->fresh()->suspension_reason)->toBe(SuspendRestaurant::BILLING_REASON)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Suspended);
    Notification::assertSentTo($owner, RestaurantSuspended::class);
});

it('reactivates a billing-suspended client as soon as they pay', function () {
    [$restaurant, $subscription] = subscribed(trialDays: 0);
    $this->travelTo(now()->addDays(16));
    app(RunBillingCycle::class)->handle();
    app(RunBillingCycle::class)->handle();
    expect($restaurant->fresh()->status)->toBe(RestaurantStatus::Suspended);

    app(MarkInvoicePaid::class)->handle(Invoice::withoutTenancy()->sole(), 'bank_transfer', 'TRX-1');

    expect($restaurant->fresh()->status)->toBe(RestaurantStatus::Active)
        ->and($restaurant->fresh()->suspended_at)->toBeNull()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('keeps a manual suspension even after payment', function () {
    [$restaurant] = subscribed(trialDays: 0);
    app(SuspendRestaurant::class)->handle($restaurant, 'Contract review');

    app(MarkInvoicePaid::class)->handle(Invoice::withoutTenancy()->sole(), 'cash');

    expect($restaurant->fresh()->status)->toBe(RestaurantStatus::Suspended);
});

it('only voids or pays open invoices', function () {
    subscribed(trialDays: 0);
    $invoice = Invoice::withoutTenancy()->sole();

    app(MarkInvoicePaid::class)->handle($invoice, 'card');

    expect(fn () => app(VoidInvoice::class)->handle($invoice->fresh(), 'mistake'))->toThrow(DomainException::class)
        ->and(fn () => app(MarkInvoicePaid::class)->handle($invoice->fresh(), 'card'))->toThrow(DomainException::class)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('computes monthly recurring revenue only from paying subscriptions', function () {
    [, $trial] = subscribed(trialDays: 14);
    [, $paying] = subscribed(branches: 2, trialDays: 0);

    expect($trial->mrrMinor())->toBe(0)->and($paying->mrrMinor())->toBe(129800);
});

it('takes service features from the service and add-ons from the plan, with overrides winning', function () {
    $plan = Plan::factory()->create(['features' => [PlanFeature::Loyalty, PlanFeature::AdvancedReports]]);
    [$restaurant] = subscribed($plan, trialDays: 0);

    // Loyalty belongs to Rewards: the plan listing it is not enough.
    expect($restaurant->hasFeature(PlanFeature::Loyalty))->toBeFalse()
        ->and($restaurant->hasFeature(PlanFeature::AdvancedReports))->toBeTrue()
        ->and($restaurant->hasFeature(PlanFeature::AiAssistant))->toBeFalse()
        ->and($restaurant->hasFeature(PlanFeature::ArrivalTracking))->toBeTrue(); // comes with Drive-Thru

    withService($restaurant, Service::Rewards);
    expect($restaurant->hasFeature(PlanFeature::Loyalty))->toBeTrue();

    $restaurant->update(['feature_overrides' => ['ai_assistant' => true, 'loyalty' => false]]);

    expect($restaurant->fresh()->hasFeature(PlanFeature::AiAssistant))->toBeTrue()
        ->and($restaurant->fresh()->hasFeature(PlanFeature::Loyalty))->toBeFalse();
});

it('renders a bilingual PDF invoice', function () {
    subscribed(trialDays: 0);

    $pdf = app(InvoicePdf::class)->render(Invoice::withoutTenancy()->sole());

    expect(substr($pdf, 0, 4))->toBe('%PDF')->and(strlen($pdf))->toBeGreaterThan(5000);
});

it('formats money in both languages with Western digits', function () {
    expect(Money::format(64900, 'QAR', 'en'))->toBe("649.00\u{00A0}QAR")
        ->and(Money::format(64900, 'QAR', 'ar'))->toBe("649.00\u{00A0}ر.ق")
        ->and(Money::format(1500, 'KWD', 'en'))->toBe("1.500\u{00A0}KWD")
        ->and(Money::toMinor('٣٤٩٫٥', 'QAR'))->toBe(34950);
});
