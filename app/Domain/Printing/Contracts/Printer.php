<?php

declare(strict_types=1);

namespace App\Domain\Printing\Contracts;

/** Sends a finished PDF to a physical printer. */
interface Printer
{
    public function name(): string;

    /** @return bool whether the provider accepted the job */
    public function send(int $restaurantId, string $printerId, string $title, string $pdf): bool;

    public function verifyConnection(): bool;
}
