<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\ReadyCall\Pager;
use Illuminate\Console\Command;

final class ExpirePagerTickets extends Command
{
    protected $signature = 'pager:expire';

    protected $description = 'Close pager tickets that are past their lifetime';

    public function handle(Pager $pager): int
    {
        $this->components->info('Expired '.$pager->expireOld().' pager tickets.');

        return self::SUCCESS;
    }
}
