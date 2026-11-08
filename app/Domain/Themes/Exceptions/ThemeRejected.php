<?php

declare(strict_types=1);

namespace App\Domain\Themes\Exceptions;

use RuntimeException;

/**
 * A theme value, file or upload that can't be used. The reason is a key
 * under themes.errors, shown to the person in their language.
 */
final class ThemeRejected extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public readonly string $reason, public readonly array $context = [])
    {
        parent::__construct($reason);
    }

    public function translated(): string
    {
        $context = array_map(fn (mixed $value): string => is_float($value) ? number_format($value, 1) : (string) (is_scalar($value) ? $value : ''), $this->context);

        return __("themes.errors.{$this->reason}", $context);
    }
}
