<?php

declare(strict_types=1);

namespace App\Domain\Branches\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** How guests reach the menu: one link for all branches, or one locked link per branch. */
enum MenuLinkMode: string implements HasDescription, HasLabel
{
    case Single = 'single';
    case PerBranch = 'per_branch';

    public function getLabel(): string
    {
        return __("settings.menu_link_mode.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("settings.menu_link_mode.{$this->value}.description");
    }
}
