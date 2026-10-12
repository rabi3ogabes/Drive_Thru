<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class TenancyViolation extends LogicException
{
    public static function missingTenant(Model $model): self
    {
        return new self(sprintf(
            'Cannot create %s without a restaurant: set restaurant_id or run inside TenantContext.',
            $model::class,
        ));
    }

    public static function crossTenantMove(Model $model): self
    {
        return new self(sprintf('A %s record cannot be moved to another restaurant.', $model::class));
    }
}
