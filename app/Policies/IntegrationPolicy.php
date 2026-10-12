<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Integrations\Models\ApiClient;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Models\User;
use App\Policies\Concerns\ChecksRestaurantPermission;

/** API keys and webhooks: people with "Integrations & API", inside their own restaurant only. */
final class IntegrationPolicy
{
    use ChecksRestaurantPermission;

    public function viewAny(User $user): bool
    {
        return $this->canInWorkspace($user, [P::ManageIntegrations]);
    }

    public function view(User $user, WebhookEndpoint|ApiClient $record): bool
    {
        return $this->inWorkspace($record->restaurant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, WebhookEndpoint|ApiClient $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, WebhookEndpoint|ApiClient $record): bool
    {
        return $this->view($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
