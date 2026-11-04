<?php

declare(strict_types=1);

namespace App\Domain\Feedback;

use App\Domain\Feedback\Models\OrderRating;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * What guests said over a period: the average, how many gave each score,
 * the tags they picked most, and a day-by-day line. Days follow the time
 * zone of $from and $to (pass them in the restaurant's time zone).
 */
final class RatingStats
{
    /**
     * @param  list<int>|null  $branchIds  null for every branch
     * @return array{count: int, average: ?float, distribution: array<int, int>, tags: array<string, int>, days: list<array{date: string, average: ?float, count: int}>}
     */
    public static function for(int $restaurantId, ?array $branchIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);

        $rows = OrderRating::withoutTenancy()
            ->where('restaurant_id', $restaurantId)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->get(['stars', 'tags', 'created_at']);

        $distribution = array_fill_keys([1, 2, 3, 4, 5], 0);
        $tags = [];
        $byDay = [];
        $tz = $to->getTimezone();

        foreach ($rows as $row) {
            $distribution[$row->stars]++;

            foreach ($row->tags as $tag) {
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }

            $day = $row->created_at->setTimezone($tz)->format('Y-m-d');
            $byDay[$day][] = $row->stars;
        }

        // Most mentioned first; ties keep the tags' own order (praise, then problems).
        $order = array_flip(RatingTags::all());
        uksort($tags, fn (string $a, string $b): int => [$tags[$b], $order[$a] ?? 99] <=> [$tags[$a], $order[$b] ?? 99]);

        $days = [];

        for ($day = $from->setTimezone($tz)->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $stars = $byDay[$day->format('Y-m-d')] ?? [];
            $days[] = ['date' => $day->format('Y-m-d'), 'average' => self::average($stars), 'count' => count($stars)];
        }

        return [
            'count' => $rows->count(),
            'average' => self::average($rows->pluck('stars')->all()),
            'distribution' => $distribution,
            'tags' => $tags,
            'days' => $days,
        ];
    }

    /**
     * @param  array<int, int>  $stars
     */
    private static function average(array $stars): ?float
    {
        return $stars === [] ? null : round(array_sum($stars) / count($stars), 1);
    }
}
