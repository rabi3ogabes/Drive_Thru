<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Actions\ExpireUnpaidOrders;
use Illuminate\Console\Command;

final class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Check unpaid online orders with their provider, then send them to the kitchen or cancel them';

    public function handle(ExpireUnpaidOrders $expire): int
    {
        $counts = $expire->handle();
        $this->components->info("Paid {$counts['paid']}, cancelled {$counts['cancelled']}, still waiting {$counts['waiting']}.");

        return self::SUCCESS;
    }
}
