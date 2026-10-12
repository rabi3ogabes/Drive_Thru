<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Data\ChargeResult;
use App\Domain\Payments\Data\ProviderStatus;
use App\Domain\Payments\Data\RefundResult;

/**
 * Every payment provider sits behind this contract. A driver only ever sees
 * the payment it works on; the restaurant's credentials are given to it by
 * the GatewayResolver. Results are plain values, never trusted until the
 * provider's signature or API has confirmed them.
 */
interface PaymentGateway
{
    public function name(): string;

    /** "test" or "live", recorded on every payment for reconciliation. */
    public function mode(): string;

    /**
     * Online methods the guest can choose with this provider.
     *
     * @return list<PaymentMethod>
     */
    public function methods(): array;

    /** Starts a charge: paid now, failed, or "send the guest to this page". */
    public function charge(Payment $payment, string $returnUrl): ChargeResult;

    /** Gives money back (all or part, when supported). */
    public function refund(Payment $payment, int $amountMinor, ?string $reason = null): RefundResult;

    public function supportsPartialRefunds(): bool;

    /** Asks the provider where a payment stands (for callbacks that never came). */
    public function lookup(Payment $payment): ProviderStatus;
}
