<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Loyalty\Actions\MaintainLoyalty;
use Illuminate\Console\Command;

final class MaintainLoyaltyCommand extends Command
{
    protected $signature = 'loyalty:maintain';

    protected $description = 'Expire old points and wallet rewards, and move guests between tiers';

    public function handle(MaintainLoyalty $maintain): int
    {
        $result = $maintain->handle();
        $this->components->info("Expired {$result['points']} points and {$result['rewards']} rewards; {$result['tiers']} tier changes.");

        return self::SUCCESS;
    }
}
