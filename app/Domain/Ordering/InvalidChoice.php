<?php

declare(strict_types=1);

namespace App\Domain\Ordering;

use RuntimeException;

/** A cart line's options or meal choices don't satisfy the menu's rules. */
final class InvalidChoice extends RuntimeException {}
