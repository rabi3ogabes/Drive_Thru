<?php

declare(strict_types=1);

namespace App\Domain\Services\Actions;

use App\Domain\Billing\Actions\IssueInvoice;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\PlanCatalog;
use App\Domain\Billing\SubscriptionCharges;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Exceptions\ServiceProblem;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Services\Notifications\ServiceChanged;
use App\Domain\Services\ServicePricing;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Every change to a restaurant's services goes through here, under a lock
 * on the restaurant's row for that service: demos (once per service unless
 * the platform grants another), keeping a demo, adding straight away (billed
 * pro rata for the rest of the period), cancelling at period end, and the
 * platform's own changes. Each change is audited and the owner is told.
 */
final class ManageServices
{
    public function __construct(
        private readonly ServicePricing $pricing,
        private readonly IssueInvoice $issueInvoice,
    ) {}

    /**
     * @throws ServiceProblem
     */
    public function startDemo(Restaurant $restaurant, Service $service, ?User $by, ?int $days = null, bool $grantAnother = false, ?string $reason = null): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($restaurant, $service, $by, $days, $grantAnother): RestaurantService {
            if ($row?->isOn()) {
                throw ServiceProblem::because('already_on', $service);
            }

            if (! $this->pricing->forSale($service) && $by?->isPlatformStaff() !== true) {
                throw ServiceProblem::because('not_for_sale', $service);
            }

            if ($row?->demo_used_at !== null && ! $grantAnother) {
                throw ServiceProblem::because('demo_used', $service);
            }

            $days = max(1, min(365, $days ?? $this->pricing->demoDays($service)));
            $row ??= new RestaurantService(['restaurant_id' => $restaurant->id, 'service' => $service]);
            $row->fill([
                'status' => ServiceStatus::Demo,
                'unit_price_minor' => $this->pricing->listPriceMinor($service),
                'demo_ends_at' => now()->addDays($days),
                'keep_after_demo' => false,
                'demo_used_at' => now(),
                'started_at' => now(),
                'ends_at' => null,
                'added_by' => $by?->id,
            ])->save();

            return $row;
        }, $grantAnother ? 'demo_granted' : 'demo_started', $by, $reason, notify: 'demo_started');
    }

    /**
     * Turns a service on and bills the rest of the current period now.
     *
     * @throws ServiceProblem
     */
    public function activate(Restaurant $restaurant, Service $service, ?User $by, ?int $unitPriceMinor = null, ?string $reason = null): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($restaurant, $service, $by, $unitPriceMinor): RestaurantService {
            if ($row !== null && $row->status->isBilled()) {
                throw ServiceProblem::because('already_on', $service);
            }

            if (! $this->pricing->forSale($service) && $by?->isPlatformStaff() !== true) {
                throw ServiceProblem::because('not_for_sale', $service);
            }

            $before = $this->pricing->restaurantQuote($restaurant);
            $row ??= new RestaurantService(['restaurant_id' => $restaurant->id, 'service' => $service]);
            $row->fill([
                'status' => ServiceStatus::Active,
                'unit_price_minor' => max(0, $unitPriceMinor ?? ($row->exists ? $row->unit_price_minor : $this->pricing->listPriceMinor($service))),
                'demo_ends_at' => null,
                'keep_after_demo' => false,
                'started_at' => now(),
                'ends_at' => null,
                'added_by' => $row->added_by ?? $by?->id,
            ])->save();

            $this->billRestOfPeriod($restaurant, $service, $before);

            return $row;
        }, 'activated', $by, $reason, notify: $by?->isPlatformStaff() ? 'added' : null);
    }

    /**
     * @throws ServiceProblem
     */
    public function keepAfterDemo(Restaurant $restaurant, Service $service, bool $keep, User $by): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($service, $keep): RestaurantService {
            if ($row === null || $row->status !== ServiceStatus::Demo) {
                throw ServiceProblem::because('not_on', $service);
            }

            $row->forceFill(['keep_after_demo' => $keep])->save();

            return $row;
        }, $keep ? 'keep_set' : 'keep_unset', $by);
    }

    /**
     * Stops at the end of the paid period (a demo stops right away).
     *
     * @throws ServiceProblem
     */
    public function cancel(Restaurant $restaurant, Service $service, User $by): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($restaurant, $service): RestaurantService {
            if ($row === null || ! $row->isOn() || $row->status === ServiceStatus::Cancelling) {
                throw ServiceProblem::because('not_on', $service);
            }

            if ($row->status === ServiceStatus::Demo) {
                $row->forceFill(['status' => ServiceStatus::Ended, 'ends_at' => now(), 'keep_after_demo' => false])->save();

                return $row;
            }

            $periodEnd = $this->subscription($restaurant)?->current_period_end;
            $row->forceFill([
                'status' => $periodEnd !== null && $periodEnd->isFuture() ? ServiceStatus::Cancelling : ServiceStatus::Ended,
                'ends_at' => $periodEnd !== null && $periodEnd->isFuture() ? $periodEnd : now(),
            ])->save();

            return $row;
        }, 'cancelling', $by);
    }

    /**
     * @throws ServiceProblem
     */
    public function resume(Restaurant $restaurant, Service $service, User $by): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($service): RestaurantService {
            if ($row === null || $row->status !== ServiceStatus::Cancelling) {
                throw ServiceProblem::because('not_on', $service);
            }

            $row->forceFill(['status' => ServiceStatus::Active, 'ends_at' => null])->save();

            return $row;
        }, 'resumed', $by);
    }

    /**
     * Platform only: switch off now, no refund.
     *
     * @throws ServiceProblem
     */
    public function end(Restaurant $restaurant, Service $service, ?User $by, ?string $reason = null): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($service): RestaurantService {
            if ($row === null || ! $row->isOn()) {
                throw ServiceProblem::because('not_on', $service);
            }

            $row->forceFill(['status' => ServiceStatus::Ended, 'ends_at' => now(), 'keep_after_demo' => false])->save();

            return $row;
        }, 'ended', $by, $reason);
    }

    /**
     * @throws ServiceProblem
     */
    public function extendDemo(Restaurant $restaurant, Service $service, int $days, User $by, ?string $reason = null): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($service, $days): RestaurantService {
            if ($row === null || $row->status !== ServiceStatus::Demo) {
                throw ServiceProblem::because('not_on', $service);
            }

            $from = $row->demo_ends_at !== null && $row->demo_ends_at->isFuture() ? $row->demo_ends_at : CarbonImmutable::now();
            $row->forceFill(['demo_ends_at' => $from->addDays(max(1, min(365, $days)))])->save();

            return $row;
        }, 'demo_extended', $by, $reason);
    }

    /**
     * The client's own price for a service, from the next invoice on.
     *
     * @throws ServiceProblem
     */
    public function setPrice(Restaurant $restaurant, Service $service, int $unitPriceMinor, User $by, ?string $reason = null): RestaurantService
    {
        return $this->change($restaurant, $service, function (?RestaurantService $row) use ($service, $unitPriceMinor): RestaurantService {
            if ($row === null || ! $row->isOn()) {
                throw ServiceProblem::because('not_on', $service);
            }

            $row->forceFill(['unit_price_minor' => max(0, $unitPriceMinor)])->save();

            return $row;
        }, 'price_changed', $by, $reason);
    }

    /**
     * The daily step of the billing run. Each row changes under its lock and
     * only from the state it was read in, so a second run the same day finds
     * nothing left to do:
     *  - demos ending within 3 days: the owner is told once;
     *  - demos that ended: kept ones become paid from today (rest of the
     *    period billed now), the others switch off;
     *  - cancelled services whose paid period is over switch off.
     *
     * @return array{reminded: int, converted: int, demos_ended: int, ended: int}
     */
    public function settle(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $report = ['reminded' => 0, 'converted' => 0, 'demos_ended' => 0, 'ended' => 0];

        RestaurantService::withoutTenancy()->with('restaurant')
            ->where('status', ServiceStatus::Demo->value)
            ->whereNull('demo_reminded_at')
            ->where('demo_ends_at', '>', $now)
            ->where('demo_ends_at', '<=', $now->addDays(3))
            ->each(function (RestaurantService $row) use (&$report): void {
                $claimed = RestaurantService::withoutTenancy()->whereKey($row->id)->whereNull('demo_reminded_at')->update(['demo_reminded_at' => now()]) === 1;

                if ($claimed && $row->restaurant !== null) {
                    $row->restaurant->owner()?->notify(new ServiceChanged($row->restaurant, $row, 'demo_ending'));
                    $report['reminded']++;
                }
            });

        RestaurantService::withoutTenancy()->with('restaurant')
            ->where('status', ServiceStatus::Demo->value)
            ->where('demo_ends_at', '<=', $now)
            ->each(function (RestaurantService $row) use (&$report): void {
                $restaurant = $row->restaurant;

                if ($restaurant === null) {
                    return;
                }

                $kept = $row->keep_after_demo;
                $before = $this->pricing->restaurantQuote($restaurant);

                try {
                    $settled = $this->change($restaurant, $row->service, function (?RestaurantService $current) use ($kept, $row): RestaurantService {
                        if ($current === null || $current->status !== ServiceStatus::Demo) {
                            throw ServiceProblem::because('not_on', $row->service); // settled by another run
                        }

                        $current->forceFill($kept
                            ? ['status' => ServiceStatus::Active, 'started_at' => now(), 'keep_after_demo' => false]
                            : ['status' => ServiceStatus::Ended, 'ends_at' => now()])->save();

                        return $current;
                    }, $kept ? 'converted' : 'ended', null, notify: $kept ? 'demo_converted' : 'demo_ended');
                } catch (ServiceProblem) {
                    return;
                }

                if ($kept) {
                    $this->billRestOfPeriod($restaurant, $settled->service, $before);
                    $report['converted']++;
                } else {
                    $report['demos_ended']++;
                }
            });

        RestaurantService::withoutTenancy()->with('restaurant')
            ->where('status', ServiceStatus::Cancelling->value)
            ->where('ends_at', '<=', $now)
            ->each(function (RestaurantService $row) use (&$report): void {
                $updated = RestaurantService::withoutTenancy()->whereKey($row->id)
                    ->where('status', ServiceStatus::Cancelling->value)
                    ->update(['status' => ServiceStatus::Ended->value, 'updated_at' => now()]);

                if ($updated === 1 && $row->restaurant !== null) {
                    activity()->performedOn($row->restaurant)->event('services.ended')
                        ->withProperties(['service' => $row->service->value, 'status' => ServiceStatus::Ended->value])->log('services.ended');
                    $report['ended']++;
                }
            });

        return $report;
    }

    /**
     * The subscription that carries the billing period, created on first use
     * (the first plan tier, no fee of its own, a monthly period from today).
     */
    public function subscription(Restaurant $restaurant, bool $create = false): ?Subscription
    {
        $subscription = Subscription::withoutTenancy()->where('restaurant_id', $restaurant->id)
            ->where('status', '!=', SubscriptionStatus::Cancelled)->latest('id')->first();

        if ($subscription !== null || ! $create) {
            return $subscription;
        }

        $plan = Plan::query()->available()->first();

        if ($plan === null) {
            app(PlanCatalog::class)->install();
            $plan = Plan::query()->available()->firstOrFail();
        }

        $now = CarbonImmutable::now();

        return Subscription::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'quantity' => 1,
            'unit_price_minor' => 0,
            'currency' => $this->pricing->currency(),
            'interval' => $plan->interval,
            'current_period_start' => $now,
            'current_period_end' => $plan->interval->addTo($now),
        ]);
    }

    /**
     * What the restaurant pays today when $service is added: the change in the
     * monthly total (bundle discount included), for the days left in the period.
     *
     * @param  array{total_minor: int}  $before
     * @return array{amount_minor: int, days: int, period_days: int, period_end: ?CarbonImmutable}
     */
    public function prorate(Restaurant $restaurant, array $before, int $afterTotalMinor): array
    {
        $subscription = $this->subscription($restaurant);
        $tz = $restaurant->timezone;
        $today = CarbonImmutable::now($tz)->startOfDay();
        $start = $subscription?->current_period_start?->setTimezone($tz)->startOfDay() ?? $today;
        $end = $subscription?->current_period_end?->setTimezone($tz)->startOfDay();

        if ($end === null || $end->lte($today)) {
            return ['amount_minor' => 0, 'days' => 0, 'period_days' => 0, 'period_end' => $end];
        }

        $periodDays = max(1, (int) $start->diffInDays($end));
        $days = min($periodDays, max(1, (int) $today->diffInDays($end)));
        $months = $subscription->interval->months();
        $delta = max(0, $afterTotalMinor - $before['total_minor']) * $months;

        return [
            'amount_minor' => (int) round($delta * $days / $periodDays),
            'days' => $days,
            'period_days' => $periodDays,
            'period_end' => $end,
        ];
    }

    /**
     * @param  array{total_minor: int}  $before
     */
    private function billRestOfPeriod(Restaurant $restaurant, Service $service, array $before): void
    {
        $subscription = $this->subscription($restaurant, create: true);

        if ($subscription === null || $subscription->status === SubscriptionStatus::Trialing) {
            return; // a whole-restaurant trial is free; the first period invoice will include it
        }

        $after = $this->pricing->restaurantQuote($restaurant);
        $proration = $this->prorate($restaurant, $before, $after['total_minor']);

        if ($proration['amount_minor'] <= 0 || $proration['period_end'] === null) {
            return;
        }

        $branches = $after['branches'];
        $line = [
            'description' => SubscriptionCharges::both(fn (string $l): string => __('services.invoice.prorated', [
                'service' => __('services.names.'.$service->value, [], $l),
                'branches' => trans_choice('services.invoice.branches', $branches, ['count' => $branches], $l),
                'days' => $proration['days'],
            ], $l)),
            'quantity' => 1,
            'unit_price_minor' => $proration['amount_minor'],
            'amount_minor' => $proration['amount_minor'],
            'is_discount' => false,
        ];

        $this->issueInvoice->oneOff($subscription, [$line], CarbonImmutable::now(), $proration['period_end']);
    }

    /**
     * Runs a change on the restaurant's row for the service, locked, then
     * audits it and tells the owner when the change is news to them.
     *
     * @param  callable(?RestaurantService): RestaurantService  $apply
     *
     * @throws ServiceProblem
     */
    private function change(Restaurant $restaurant, Service $service, callable $apply, string $event, ?User $by, ?string $reason = null, ?string $notify = null): RestaurantService
    {
        $row = DB::transaction(function () use ($restaurant, $service, $apply): RestaurantService {
            $row = RestaurantService::withoutTenancy()
                ->where('restaurant_id', $restaurant->id)
                ->where('service', $service->value)
                ->lockForUpdate()
                ->first();

            return $apply($row);
        });

        $restaurant->unsetRelation('services');

        $log = activity()->performedOn($restaurant)->event('services.'.$event)
            ->withProperties(array_filter([
                'service' => $service->value,
                'status' => $row->status->value,
                'unit_price_minor' => $row->unit_price_minor,
                'demo_ends_at' => $row->demo_ends_at?->toIso8601String(),
                'reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 300) : null,
            ], fn (mixed $v): bool => $v !== null));

        if ($by !== null) {
            $log->causedBy($by);
        }

        $log->log('services.'.$event);

        if ($notify !== null) {
            $restaurant->owner()?->notify(new ServiceChanged($restaurant, $row, $notify));
        }

        return $row;
    }
}
