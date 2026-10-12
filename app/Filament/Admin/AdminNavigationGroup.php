<?php

declare(strict_types=1);

namespace App\Filament\Admin;

use Filament\Navigation\NavigationGroup;
use Filament\Support\Contracts\HasLabel;

/**
 * Sidebar groups of the platform console, in display order.
 */
enum AdminNavigationGroup: string implements HasLabel
{
    case Clients = 'clients';
    case Billing = 'billing';
    case Platform = 'platform';
    case Security = 'security';

    public function getLabel(): string
    {
        return __("admin.nav.{$this->value}");
    }

    /**
     * Filament's own enum support reads labels once at boot, before the
     * visitor's language is known. These groups translate on every request.
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
