<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Data;

use App\Domain\Billing\Models\Plan;

/**
 * Everything needed to open a new client account.
 */
final readonly class OnboardClientData
{
    /**
     * @param  array{ar: string, en: string}  $name
     */
    public function __construct(
        public array $name,
        public string $ownerName,
        public string $ownerEmail,
        public Plan $plan,
        public ?string $ownerPhone = null,
        public ?string $slug = null,
        public int $branches = 1,
        public ?int $trialDays = null,
        public ?int $unitPriceMinor = null,
        public string $timezone = 'Asia/Qatar',
        public string $locale = 'ar',
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public bool $sendInvitation = true,
    ) {}
}
