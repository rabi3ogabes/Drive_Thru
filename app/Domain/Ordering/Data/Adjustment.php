<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Data;

/**
 * A line under the subtotal: a promotion, coupon, reward or points, with
 * the amount it takes off (positive) and any bonus points it gives.
 */
final readonly class Adjustment
{
    public const PROMOTION = 'promotion';

    public const COUPON = 'coupon';

    public const REWARD = 'reward';

    public const POINTS = 'points';

    /**
     * @param  array<string, string>  $label  ['en' => …, 'ar' => …]
     */
    public function __construct(
        public string $kind,
        public int $sourceId,
        public array $label,
        public int $amountMinor,
        public int $bonusPoints = 0,
    ) {}

    public function withAmount(int $amountMinor): self
    {
        return new self($this->kind, $this->sourceId, $this->label, $amountMinor, $this->bonusPoints);
    }

    public function localLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->label[$locale] ?? (array_values($this->label)[0] ?? '');
    }
}
