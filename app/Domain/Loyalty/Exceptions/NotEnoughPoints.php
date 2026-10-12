<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Exceptions;

use RuntimeException;

/** The guest's balance changed (or never had) enough points for this. */
final class NotEnoughPoints extends RuntimeException {}
