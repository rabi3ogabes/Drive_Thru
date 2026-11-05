<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Guide\GuideArticle;
use App\Domain\Guide\GuideCatalog;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Http\Controllers\OpsController;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * The owner guide: what every workspace page, button and branch screen does,
 * in Arabic and English. Each page's "?" opens its article in a side panel.
 */
class Guide extends Page
{
    /** Section icons, in GuideCatalog::SECTIONS order. */
    public const ICONS = [
        'operations' => 'heroicon-o-signal',
        'menu' => 'heroicon-o-squares-2x2',
        'growth' => 'heroicon-o-sparkles',
        'branches' => 'heroicon-o-building-storefront',
        'team' => 'heroicon-o-user-group',
        'restaurant' => 'heroicon-o-cog-6-tooth',
        'screens' => 'heroicon-o-tv',
        'guests' => 'heroicon-o-device-phone-mobile',
    ];

    protected static ?string $slug = 'guide';

    protected string $view = 'filament.app.pages.guide';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 99;

    public static function getNavigationLabel(): string
    {
        return __('guide.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('guide.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('guide.ui.subheading');
    }

    /**
     * @return list<array{key: string, section: string, title: string, summary: string, when: string, actions: array<string, string>, tips: list<string>, who: string, available: bool, url: ?string, external: bool, search: string}>
     */
    public static function entries(): array
    {
        return array_map(self::entry(...), GuideCatalog::all());
    }

    /**
     * What a reader sees of one article. "url" is null when the restaurant's
     * plan lacks it or this person can't open the page, so no dead button shows.
     *
     * @return array{key: string, section: string, title: string, summary: string, when: string, actions: array<string, string>, tips: list<string>, who: string, available: bool, url: ?string, external: bool, search: string}
     */
    public static function entry(GuideArticle $article): array
    {
        $restaurant = Workspace::restaurant();
        $available = $article->availableTo($restaurant);
        $actions = $article->actions();

        return [
            'key' => $article->key,
            'section' => $article->section,
            'title' => $article->title(),
            'summary' => $article->summary(),
            'when' => $article->when(),
            'actions' => $actions,
            'tips' => $article->tips(),
            'who' => match (true) {
                $article->section === 'guests' => __('guide.ui.guests'),
                $article->permission !== null => $article->permission->getLabel(),
                default => __('guide.ui.everyone'),
            },
            'available' => $available,
            'url' => $available && self::canOpen($article) ? $article->url($restaurant) : null,
            'external' => $article->page === null,
            'search' => implode(' ', [$article->title(), $article->summary(), $article->when(), ...array_keys($actions), ...array_values($actions), ...$article->tips()]),
        ];
    }

    /**
     * @return array<string, array{title: string, summary: string, icon: string, entries: list<array<string, mixed>>}>
     */
    public function sections(): array
    {
        $entries = self::entries();
        $sections = [];

        foreach (GuideCatalog::SECTIONS as $key) {
            $sections[$key] = [
                'title' => __("guide.sections.{$key}.title"),
                'summary' => __("guide.sections.{$key}.summary"),
                'icon' => self::ICONS[$key],
                'entries' => array_values(array_filter($entries, fn (array $entry): bool => $entry['section'] === $key)),
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{key: string, title: string, step: string, url: ?string}>
     */
    public function startHere(): array
    {
        return array_map(fn (GuideArticle $article): array => [
            'key' => $article->key,
            'title' => $article->title(),
            'step' => __("guide.start.steps.{$article->key}"),
            'url' => self::canOpen($article) ? $article->url(Workspace::restaurant()) : null,
        ], GuideCatalog::startHere());
    }

    /**
     * The "?" for a page header (PAGE_HEADER_ACTIONS_AFTER), or nothing for a
     * page without an article, such as the guide itself.
     *
     * @param  array<int, string>  $scopes  The page class, then its resource.
     */
    public static function helpButton(array $scopes): string
    {
        $article = isset($scopes[0]) ? GuideCatalog::forPage($scopes[0]) : null;

        if ($article === null) {
            return '';
        }

        return view('filament.app.guide.help-button', ['entry' => self::entry($article), 'guide' => self::getUrl()])->render();
    }

    private static function canOpen(GuideArticle $article): bool
    {
        if ($article->page !== null) {
            /** @var class-string<Page>|class-string<\Filament\Resources\Resource> $page */
            $page = $article->page;

            return $page::canAccess();
        }

        $restaurant = Workspace::restaurant();
        $user = Workspace::user();
        $screen = $article->routeParameters['screen'] ?? null;

        return match (true) {
            $article->section === 'guests' => true,
            $article->route === 'ops.overview' => app(ChannelAccess::class)->restaurantDashboard($user, $restaurant->id),
            $screen !== null => Operator::staff($user, $restaurant->id)->can(...OpsController::SCREENS[$screen][1]),
            default => false,
        };
    }
}
