<?php

declare(strict_types=1);

namespace App\Filament\App;

use Filament\Navigation\NavigationGroup;
use Filament\Support\Contracts\HasLabel;

/**
 * Sidebar groups of the restaurant workspace, in display order.
 */
enum WorkspaceNavigationGroup: string implements HasLabel
{
    case Operations = 'operations';
    case Menu = 'menu';
    case Growth = 'growth';
    case Branches = 'branches';
    case Team = 'team';
    case Restaurant = 'restaurant';

    public function getLabel(): string
    {
        return __("workspace.nav.{$this->value}");
    }

    /**
     * Translated on every request (Filament's enum support reads labels once at boot).
     *
     * @return array<string, NavigationGroup>
     */
    public static function navigationGroups(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [
                $case->name => NavigationGroup::make()->label(fn (): string => $case->getLabel()),
            ])
            ->all();
    }
}
