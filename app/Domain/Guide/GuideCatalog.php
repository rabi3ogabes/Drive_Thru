<?php

declare(strict_types=1);

namespace App\Domain\Guide;

use App\Domain\Access\Enums\RestaurantPermission as P;
use App\Domain\Billing\Enums\PlanFeature as F;
use App\Domain\Services\Enums\Service;
use App\Filament\App\Pages;
use App\Filament\App\Resources;
use App\Filament\Pages\AlertPreferences;
use App\Filament\Pages\DeviceSessions;
use App\Http\Controllers\OpsController;
use Filament\Resources\Pages\Page as ResourcePage;

/**
 * Every article of the owner guide, in reading order. Each workspace page and
 * resource has one (GuideCatalogTest fails otherwise), plus the branch
 * screens and the pages guests see.
 */
final class GuideCatalog
{
    /** Section order on the guide page; it follows the workspace sidebar. */
    public const SECTIONS = ['operations', 'menu', 'growth', 'branches', 'team', 'restaurant', 'screens', 'guests'];

    /** The first-week path for a new owner. */
    public const START_HERE = ['settings', 'branches', 'products', 'menu_design', 'team', 'screens', 'links'];

    /** @var list<GuideArticle>|null */
    private static ?array $articles = null;

    /** @return list<GuideArticle> */
    public static function all(): array
    {
        return self::$articles ??= self::build();
    }

    /** @return array<string, list<GuideArticle>> */
    public static function sections(): array
    {
        $sections = array_fill_keys(self::SECTIONS, []);

        foreach (self::all() as $article) {
            $sections[$article->section][] = $article;
        }

        return $sections;
    }

    /** @return list<GuideArticle> */
    public static function startHere(): array
    {
        return array_values(array_filter(array_map(self::find(...), self::START_HERE)));
    }

    public static function find(string $key): ?GuideArticle
    {
        foreach (self::all() as $article) {
            if ($article->key === $key) {
                return $article;
            }
        }

        return null;
    }

    /** The article of a page, a resource, or any page of a resource (list, edit, view). */
    public static function forPage(string $class): ?GuideArticle
    {
        if (is_subclass_of($class, ResourcePage::class)) {
            $class = $class::getResource();
        }

        foreach (self::all() as $article) {
            if ($article->page === $class) {
                return $article;
            }
        }

        return null;
    }

    /** @return list<GuideArticle> */
    private static function build(): array
    {
        $articles = [
            new GuideArticle('dashboard', 'operations', Pages\Dashboard::class),
            new GuideArticle('all_branches', 'operations', route: 'ops.overview'),
            new GuideArticle('ratings', 'operations', Resources\Ratings\RatingResource::class, P::ViewReports, Service::DriveThru),
            new GuideArticle('support', 'operations', Resources\SupportTickets\SupportTicketResource::class, P::HandleSupport, Service::DriveThru),

            new GuideArticle('products', 'menu', Resources\Menu\ProductResource::class, P::ManageMenu),
            new GuideArticle('categories', 'menu', Resources\Menu\MenuCategoryResource::class, P::ManageMenu),
            new GuideArticle('modifiers', 'menu', Resources\Menu\ModifierGroupResource::class, P::ManageMenu),
            new GuideArticle('schedules', 'menu', Resources\Menu\MenuScheduleResource::class, P::ManageMenu),
            new GuideArticle('menu_design', 'menu', Pages\MenuDesign::class, P::ManageMenu),
            new GuideArticle('branch_menu', 'menu', Pages\BranchMenuPage::class, P::ManageAvailability),

            new GuideArticle('guests', 'growth', Resources\Customers\CustomerResource::class, P::ViewCustomers),
            new GuideArticle('loyalty', 'growth', Pages\LoyaltySettings::class, P::ManageLoyalty, feature: F::Loyalty),
            new GuideArticle('rewards', 'growth', Resources\Rewards\RewardResource::class, P::ManageLoyalty, feature: F::Loyalty),
            new GuideArticle('stamps', 'growth', Resources\StampPrograms\StampProgramResource::class, P::ManageLoyalty, feature: F::Loyalty),
            new GuideArticle('promotions', 'growth', Resources\Promotions\PromotionResource::class, P::ManagePromotions, feature: F::Promotions),
            new GuideArticle('campaigns', 'growth', Resources\Campaigns\CampaignResource::class, P::ManageCampaigns, feature: F::Campaigns),
            new GuideArticle('qr_codes', 'growth', Resources\QrCampaigns\QrCampaignResource::class, P::ManageCampaigns, feature: F::Campaigns),
            new GuideArticle('referrals', 'growth', Resources\Referrals\ReferralResource::class, P::ManageCampaigns, feature: F::Loyalty),
            new GuideArticle('guest_rules', 'growth', Pages\GuestSettings::class, P::ManageSettings),

            new GuideArticle('branches', 'branches', Resources\Branches\BranchResource::class, P::ManageBranches),
            new GuideArticle('screens', 'branches', Resources\Screens\DeviceResource::class, P::ManageBranches),
            new GuideArticle('links', 'branches', Pages\LinksCenter::class, P::ManageBranches),

            new GuideArticle('team', 'team', Resources\Team\EmployeeResource::class, P::ManageStaff),
            new GuideArticle('positions', 'team', Resources\Positions\PositionResource::class, P::ManageStaff),

            new GuideArticle('settings', 'restaurant', Pages\RestaurantSettings::class, P::ManageSettings),
            new GuideArticle('billing', 'restaurant', Pages\Billing::class, P::ManageBilling),
            new GuideArticle('integrations', 'restaurant', Pages\Integrations::class, P::ManageIntegrations),
            new GuideArticle('webhooks', 'restaurant', Resources\Webhooks\WebhookEndpointResource::class, P::ManageIntegrations),
            new GuideArticle('api_keys', 'restaurant', Resources\ApiKeys\ApiClientResource::class, P::ManageIntegrations),
            new GuideArticle('alerts', 'restaurant', AlertPreferences::class),
            new GuideArticle('devices', 'restaurant', DeviceSessions::class),
        ];

        foreach (array_keys(OpsController::SCREENS) as $screen) {
            $articles[] = new GuideArticle(
                'screen_'.$screen,
                'screens',
                permission: OpsController::SCREENS[$screen][1][0],
                service: OpsController::serviceFor($screen),
                route: 'ops.go',
                routeParameters: ['screen' => $screen],
            );
        }

        return [
            ...$articles,
            new GuideArticle('guest_ordering', 'guests', route: 'public.restaurant'),
            new GuideArticle('guest_order', 'guests', service: Service::DriveThru),
            new GuideArticle('guest_rewards', 'guests', feature: F::Loyalty, route: 'customer.rewards'),
            new GuideArticle('guest_invite', 'guests', feature: F::Loyalty),
        ];
    }
}
