<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Themes\Models\MenuTheme;
use App\Models\User;
use App\Policies\Concerns\ChecksPlatformPermission;

/**
 * The theme template library: platform staff with "Menu themes". Built-in
 * templates and templates a restaurant uses are hidden, never deleted.
 */
final class MenuThemePolicy
{
    use ChecksPlatformPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageThemes);
    }

    public function view(User $user, MenuTheme $theme): bool
    {
        return $this->allowed($user, PlatformPermission::ManageThemes);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, PlatformPermission::ManageThemes);
    }

    public function update(User $user, MenuTheme $theme): bool
    {
        return $this->allowed($user, PlatformPermission::ManageThemes);
    }

    public function delete(User $user, MenuTheme $theme): bool
    {
        return $this->allowed($user, PlatformPermission::ManageThemes)
            && ! $theme->is_built_in
            && ! $theme->is_default
            && $theme->usedByCount() === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
