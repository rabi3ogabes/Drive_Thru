<?php

declare(strict_types=1);

namespace App\Domain\Printing;

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Ordering\Models\Order;

/**
 * Prints an order's kitchen ticket or receipt on the branch's printer when
 * the restaurant has PrintNode set up and the branch has a printer for it.
 * Otherwise the screen offers the browser print dialog instead.
 */
final class PrintOrder
{
    public const SENT = 'sent';

    public const BROWSER = 'browser';

    public const FAILED = 'failed';

    public function __construct(
        private readonly TicketRenderer $tickets,
        private readonly ProviderClient $client,
    ) {}

    public function handle(Order $order, string $kind): string
    {
        $order->loadMissing('branch');
        $printerId = $order->branch->printers[$kind] ?? null;
        $integration = Integration::withoutTenancy()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('kind', IntegrationKind::Printing->value)
            ->where('is_enabled', true)
            ->first();

        if ($integration === null || $integration->credential('api_key') === null || $printerId === null || $printerId === '') {
            return self::BROWSER;
        }

        $printer = new PrintNodePrinter($integration, $this->client);
        $title = $order->displayNumber().' · '.__('print.'.$kind);

        return $printer->send($order->restaurant_id, (string) $printerId, $title, $this->tickets->pdf($order, $kind)) ? self::SENT : self::FAILED;
    }

    /** Whether this branch prints by itself (so screens show "Sent to printer"). */
    public function hasPrinter(Order $order, string $kind): bool
    {
        $order->loadMissing('branch');

        return ($order->branch->printers[$kind] ?? null) !== null
            && Integration::withoutTenancy()->where('restaurant_id', $order->restaurant_id)->where('kind', IntegrationKind::Printing->value)->where('is_enabled', true)->exists();
    }
}
