<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Exceptions;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Support\Lang;
use RuntimeException;

/** A status change that isn't allowed, or that someone else already made. The message is ready to show staff. */
final class InvalidTransition extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?OrderStatus $from = null,
        public readonly ?OrderStatus $to = null,
    ) {
        parent::__construct($message);
    }

    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self(Lang::text('ordering.transition.not_allowed', ['from' => $from->getLabel(), 'to' => $to->getLabel()]), $from, $to);
    }

    /** The order changed on another screen between reading and saving. */
    public static function stale(OrderStatus $expected, OrderStatus $to): self
    {
        return new self(Lang::text('ordering.transition.stale'), $expected, $to);
    }

    public static function reasonRequired(): self
    {
        return new self(Lang::text('ordering.transition.reason_required'));
    }
}
