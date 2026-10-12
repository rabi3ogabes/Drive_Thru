<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ordering\Actions\ReleaseScheduledOrders;
use Illuminate\Console\Command;

final class ReleaseScheduledOrdersCommand extends Command
{
    protected $signature = 'orders:release';

    protected $description = 'Send held orders whose start time has come to the kitchen';

    public function handle(ReleaseScheduledOrders $release): int
    {
        $count = $release->handle();
        $this->components->info("Released {$count} order(s).");

        return self::SUCCESS;
    }
}
