<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Crm\Segments;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;
use Throwable;

final class ComputeSegments extends Command
{
    protected $signature = 'crm:segments {restaurant? : Only this restaurant (slug)}';

    protected $description = 'Refresh guest stats and recompute guest segments for every restaurant';

    public function handle(Segments $segments): int
    {
        $slug = $this->argument('restaurant');
        $ids = is_string($slug)
            ? Restaurant::query()->where('slug', $slug)->pluck('id')->all()
            : Segments::restaurantIds();

        $done = 0;

        foreach (Restaurant::query()->whereKey($ids)->get() as $restaurant) {
            try {
                $segments->compute($restaurant);
                $done++;
            } catch (Throwable $e) {
                report($e); // one restaurant's bad data never stops the others
            }
        }

        $this->components->info("Segments computed for {$done} restaurants.");

        return self::SUCCESS;
    }
}
