<?php

declare(strict_types=1);

namespace App\Domain\Branches\Data;

use App\Domain\Branches\Models\Device;
use Carbon\CarbonImmutable;

/** A freshly issued pairing code; the plain code exists only in this object. */
final readonly class PairingCode
{
    public function __construct(
        public Device $device,
        public string $code,
        public CarbonImmutable $expiresAt,
    ) {}

    /** "482 913": easier to read aloud and type on a tablet. */
    public function formatted(): string
    {
        return substr($this->code, 0, 3).' '.substr($this->code, 3);
    }
}
