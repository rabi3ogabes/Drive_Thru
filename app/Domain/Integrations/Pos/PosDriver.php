<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Pos;

use App\Domain\Integrations\Exceptions\ProviderUnavailable;
use App\Domain\Ordering\Models\Order;

/** A restaurant's point-of-sale system that receives each accepted order. */
interface PosDriver
{
    public function name(): string;

    /**
     * @throws PosRejected when the POS answered but did not accept the order
     * @throws ProviderUnavailable when it can't be reached
     */
    public function pushOrder(Order $order): ?string;

    public function verifyConnection(): bool;
}
