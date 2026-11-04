<?php

declare(strict_types=1);

namespace App\Domain\Feedback;

/** The quick tags a guest can add: praise for 4-5 stars, what went wrong for 1-3. */
final class RatingTags
{
    public const POSITIVE = ['fast', 'hot_fresh', 'friendly', 'accurate'];

    public const NEGATIVE = ['slow', 'cold', 'missing_item', 'wrong_order', 'unfriendly'];

    /** Tags that point to a problem the guest can report as a ticket. */
    public const REPORTABLE = ['missing_item', 'wrong_order'];

    /**
     * @return list<string>
     */
    public static function for(int $stars): array
    {
        return $stars >= 4 ? self::POSITIVE : self::NEGATIVE;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::POSITIVE, ...self::NEGATIVE];
    }
}
