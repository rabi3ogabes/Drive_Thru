<?php

declare(strict_types=1);

namespace App\Domain\Security\Data;

use App\Support\UserAgent;
use Carbon\CarbonImmutable;

/**
 * One signed-in browser of a staff account, as shown on "Devices & sessions".
 */
final readonly class DeviceSession
{
    public function __construct(
        public string $id,
        public string $browser,
        public string $platform,
        public bool $mobile,
        public ?string $ip,
        public CarbonImmutable $lastActive,
        public bool $current,
    ) {}

    /**
     * Builds from a raw row of the sessions table.
     */
    public static function fromRow(object $row, string $currentSessionId): self
    {
        $id = is_string($row->id ?? null) ? $row->id : '';
        $agent = UserAgent::describe(is_string($row->user_agent ?? null) ? $row->user_agent : null);

        return new self(
            id: $id,
            browser: $agent['browser'],
            platform: $agent['platform'],
            mobile: $agent['mobile'],
            ip: is_string($row->ip_address ?? null) ? $row->ip_address : null,
            lastActive: CarbonImmutable::createFromTimestamp(is_numeric($row->last_activity ?? null) ? (int) $row->last_activity : 0),
            current: $id !== '' && hash_equals($currentSessionId, $id),
        );
    }
}
