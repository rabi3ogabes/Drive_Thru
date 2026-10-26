<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Actions\RunBillingCycle;
use Illuminate\Console\Command;

/**
 * Daily billing run (scheduled in routes/console.php). Safe to run by hand.
 */
final class RunBilling extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Convert ended trials, renew periods, flag overdue clients and suspend unpaid ones';

    public function handle(RunBillingCycle $cycle): int
    {
        $report = $cycle->handle();

        $this->components->twoColumnDetail('Service demos converted', (string) $report['services']['converted']);
        $this->components->twoColumnDetail('Service demos ended', (string) $report['services']['demos_ended']);
        $this->components->twoColumnDetail('Services ended', (string) $report['services']['ended']);
        $this->components->twoColumnDetail('Trials converted', (string) $report['trials_converted']);
        $this->components->twoColumnDetail('Periods renewed', (string) $report['periods_renewed']);
        $this->components->twoColumnDetail('Marked past due', (string) $report['marked_past_due']);
        $this->components->twoColumnDetail('Suspended', (string) $report['suspended']);

        return self::SUCCESS;
    }
}
