<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

final class InvoicePolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling);
    }

    /** Platform billing, or the restaurant's own people with billing access. */
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allowed($user, PlatformPermission::ManageBilling)
            || $user->hasRestaurantPermission($invoice->restaurant_id, RestaurantPermission::ManageBilling);
    }

    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Open && $this->allowed($user, PlatformPermission::ManageBilling);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Open && $this->allowed($user, PlatformPermission::ManageBilling);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
