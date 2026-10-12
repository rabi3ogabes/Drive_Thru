<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\StaffAlerts;
use App\Domain\Payments\Events\PaymentRefunded;
use App\Domain\Payments\Models\Refund;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;

/** A refund the provider refused is money a guest is owed: tell the branch and the owner right away. */
final class AlertOnFailedRefund implements ShouldQueue
{
    public function __construct(private readonly StaffAlerts $alerts) {}

    public function handle(PaymentRefunded $event): void
    {
        $refund = $event->refund;

        if ($refund->status !== Refund::FAILED) {
            return;
        }

        $order = $refund->payment()->withoutGlobalScopes()->first()?->order()->withoutGlobalScopes()->with(['branch', 'restaurant'])->first();

        if ($order === null) {
            return;
        }

        $this->alerts->send(AlertKind::RefundFailed, $order->restaurant_id, $order->branch, [
            'number' => $order->number,
            'amount' => [
                'ar' => Money::format($refund->amount_minor, $order->currency, 'ar'),
                'en' => Money::format($refund->amount_minor, $order->currency, 'en'),
            ],
            'branch' => $order->branch->getTranslations('name'),
        ], route('ops.screen', [$order->restaurant, $order->branch->code, 'live']));
    }
}
