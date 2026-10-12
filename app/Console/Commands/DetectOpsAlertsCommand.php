<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Alerts\DetectOpsAlerts;
use Illuminate\Console\Command;

final class DetectOpsAlertsCommand extends Command
{
    protected $signature = 'ops:alerts';

    protected $description = 'Alert branch teams about late orders, guests waiting at the window and overloaded kitchens';

    public function handle(DetectOpsAlerts $detect): int
    {
        $counts = $detect->handle();
        $this->components->info("Delayed {$counts['delayed']}, waiting {$counts['waiting']}, overloaded {$counts['overloaded']}.");

        return self::SUCCESS;
    }
}
