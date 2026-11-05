<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Guest problem reports: "Handle support" in the restaurant being worked in.
 * Guests open them; staff answer and resolve them, never create or delete.
 */
final class SupportTicketPolicy extends GrowthPolicy
{
    protected function permission(): RestaurantPermission
    {
        return RestaurantPermission::HandleSupport;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
