<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Loyalty\Birthdays\SendBirthdayGifts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SendBirthdayGiftsCommand extends Command
{
    protected $signature = 'loyalty:birthdays';

    protected $description = 'Give the birthday gift to guests whose birthday is coming up';

    public function handle(SendBirthdayGifts $gifts): int
    {
        $sent = $gifts->runAll(CarbonImmutable::now());
        $this->components->info("Sent {$sent} birthday gifts.");

        return self::SUCCESS;
    }
}
