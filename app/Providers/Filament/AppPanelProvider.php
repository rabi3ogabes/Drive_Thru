<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\App\OperationsNavigation;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Concerns\ConfiguresPanel;
use App\Filament\Pages\AlertPreferences;
use App\Http\Middleware\ApplyTenantContext;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

/**
 * The restaurant workspace at /app/{restaurant}: owners and their team.
 * Every page runs inside one restaurant (Filament tenancy + TenantContext).
 */
class AppPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureSharedPanel(
            $panel
                ->id('app')
                ->path('app')
                ->tenant(Restaurant::class, slugAttribute: 'slug', ownershipRelationship: 'restaurant')
                ->tenantMiddleware([ApplyTenantContext::class], isPersistent: true)
                ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('filament.components.impersonation-banner')->render())
                ->renderHook(PanelsRenderHook::CONTENT_START, fn (): string => Blade::render('<livewire:workspace-notices />'))
                ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('filament.components.live-alerts')->render())
                ->pages([AlertPreferences::class])
                ->userMenuItems([
                    Action::make('alerts')
                        ->label(fn (): string => __('alerts.preferences.menu'))
                        ->icon('heroicon-o-bell-alert')
                        ->url(fn (): string => AlertPreferences::getUrl()),
                ])
                ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
                ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
                ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
                ->pages([Dashboard::class])
                ->navigationGroups(WorkspaceNavigationGroup::navigationGroups())
                ->navigationItems(OperationsNavigation::items()),
            subtitleKey: 'app.panels.app',
            requireMfa: false,
        );
    }
}
