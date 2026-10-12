<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Auth\Login;
use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\DeviceSessions;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\SetLocale;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Everything the platform and restaurant panels share: brand, theme, fonts,
 * security, localization and account pages.
 */
trait ConfiguresPanel
{
    protected function configureSharedPanel(Panel $panel, string $subtitleKey, bool $requireMfa): Panel
    {
        return $panel
            ->login(Login::class)
            ->passwordReset()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: $requireMfa,
            )
            ->brandName(config('app.name'))
            ->brandLogo(fn () => view('components.brand.logo', ['subtitle' => __($subtitleKey)]))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.svg'))
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->viteTheme('resources/css/filament/theme.css')
            ->font('Geist Variable', provider: LocalFontProvider::class)
            ->serifFont('Fraunces Variable', provider: LocalFontProvider::class)
            ->colors([
                'primary' => Palette::teal(),
                'gray' => Palette::ink(),
                'warning' => Palette::brass(),
            ])
            ->defaultThemeMode(ThemeMode::System)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->unsavedChangesAlerts()
            ->spa()
            ->pages([DeviceSessions::class])
            ->userMenuItems([
                Action::make('sessions')
                    ->label(fn (): string => __('security.sessions.menu'))
                    ->icon('heroicon-o-device-phone-mobile')
                    ->url(fn (): string => DeviceSessions::getUrl()),
            ])
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => view('filament.components.locale-switcher')->render())
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): string => view('filament.components.simple-layout-toolbar')->render())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => view('components.meta.app-meta', ['themeColor' => '#0a0f1c'])->render())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Before DispatchServingFilamentEvent, which builds navigation labels.
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([SetLocale::class, GuardImpersonation::class], isPersistent: true)
            ->authMiddleware([Authenticate::class]);
    }
}
