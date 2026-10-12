<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Concerns\ConfiguresPanel;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * The platform console at /admin: the super admin and platform staff.
 */
class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureSharedPanel(
            $panel
                ->id('admin')
                ->path('admin')
                ->default()
                ->navigationGroups(AdminNavigationGroup::navigationGroups())
                ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
                ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
                ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
                ->pages([Dashboard::class]),
            subtitleKey: 'app.panels.admin',
            requireMfa: (bool) config('security.require_platform_mfa'),
        );
    }
}
