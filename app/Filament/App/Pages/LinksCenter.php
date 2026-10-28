<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Branches\Links;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\QrCodes;
use App\Domain\Loyalty\Stamps;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Every guest-facing link in one place: copy it, open it, or download its QR
 * as PNG, SVG or a print-ready poster.
 */
class LinksCenter extends Page
{
    protected static ?string $slug = 'links';

    protected string $view = 'filament.app.pages.links-center';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Branches;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Workspace::can(RestaurantPermission::ManageSettings)
            || Workspace::can(RestaurantPermission::ManageBranches)
            || Workspace::can(RestaurantPermission::OperateBranch);
    }

    public static function getNavigationLabel(): string
    {
        return __('links.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('links.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('links.subheading');
    }

    /**
     * @return list<array{key: string, name: string, hint: string, url: string, display: string, qr: string, branch: ?string, kind: string}>
     */
    public function links(): array
    {
        $restaurant = Workspace::restaurant();
        $links = app(Links::class);
        $qr = app(QrCodes::class);

        $items = [];
        $stamps = app(Stamps::class)->enabledFor($restaurant) && app(Stamps::class)->programs($restaurant->id)->isNotEmpty();

        if (Workspace::branchIds() === null) {
            $url = $links->restaurant($restaurant);
            $items[] = [
                'key' => 'restaurant',
                'name' => __('links.restaurant_link'),
                'hint' => $restaurant->menu_link_mode->value === 'single' ? __('links.restaurant_hint_single') : __('links.restaurant_hint_per_branch'),
                'url' => $url,
                'display' => Links::display($url),
                'qr' => $qr->svg($url),
                'branch' => null,
                'kind' => 'order',
            ];
        }

        Workspace::scopeToBranches(Branch::query())->orderBy('sort')->orderBy('id')->get()
            ->each(function (Branch $branch) use (&$items, $links, $qr, $restaurant, $stamps): void {
                $url = $links->branch($restaurant, $branch);
                $items[] = [
                    'key' => 'branch-'.$branch->id,
                    'name' => $branch->name,
                    'hint' => __('links.branch_hint'),
                    'url' => $url,
                    'display' => Links::display($url),
                    'qr' => $qr->svg($url),
                    'branch' => $branch->code,
                    'kind' => 'order',
                ];

                if ($stamps) {
                    $counter = $links->stampCounter($restaurant, $branch);
                    $items[] = [
                        'key' => 'stamps-'.$branch->id,
                        'name' => __('links.stamps_name', ['branch' => $branch->name]),
                        'hint' => __('links.stamps_hint'),
                        'url' => $counter,
                        'display' => Links::display($counter),
                        'qr' => $qr->svg($counter),
                        'branch' => $branch->code,
                        'kind' => 'stamps',
                    ];
                }
            });

        return $items;
    }
}
