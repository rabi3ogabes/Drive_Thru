<?php

declare(strict_types=1);

namespace App\Domain\Guide;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use Filament\Resources\Resource;

/**
 * One page of the owner guide. The words live in lang/{ar,en}/guide.php under
 * articles.<key>; this holds what the article explains and where it leads.
 */
final readonly class GuideArticle
{
    /**
     * @param  class-string|null  $page  The workspace page or resource it explains.
     * @param  string|null  $route  A named route for screens and guest pages outside the workspace.
     * @param  array<string, string>  $routeParameters
     */
    public function __construct(
        public string $key,
        public string $section,
        public ?string $page = null,
        public ?RestaurantPermission $permission = null,
        public ?Service $service = null,
        public ?PlanFeature $feature = null,
        public ?string $route = null,
        public array $routeParameters = [],
    ) {}

    public function title(): string
    {
        return $this->text('title');
    }

    public function summary(): string
    {
        return $this->text('summary');
    }

    public function when(): string
    {
        return $this->text('when');
    }

    /** @return array<string, string> Button label => what it does. */
    public function actions(): array
    {
        $rows = __("guide.articles.{$this->key}.actions");

        if (! is_array($rows)) {
            return [];
        }

        $actions = [];

        foreach ($rows as $row) {
            if (is_array($row) && isset($row[0], $row[1])) {
                $actions[(string) $row[0]] = (string) $row[1];
            }
        }

        return $actions;
    }

    /** @return list<string> */
    public function tips(): array
    {
        $tips = __("guide.articles.{$this->key}.tips");

        return is_array($tips) ? array_values(array_map(strval(...), $tips)) : [];
    }

    /** Where "Open this page" leads, or null for pages a guest reaches from a link (an order page). */
    public function url(Restaurant $restaurant): ?string
    {
        if ($this->route !== null) {
            return route($this->route, ['restaurant' => $restaurant, ...$this->routeParameters]);
        }

        if ($this->page === null) {
            return null;
        }

        return is_subclass_of($this->page, Resource::class)
            ? $this->page::getUrl('index', panel: 'app', tenant: $restaurant)
            : $this->page::getUrl(panel: 'app', tenant: $restaurant);
    }

    /** False when the restaurant's plan or services don't include it ("Not on your plan"). */
    public function availableTo(Restaurant $restaurant): bool
    {
        return ($this->service === null || $restaurant->hasService($this->service))
            && ($this->feature === null || $restaurant->hasFeature($this->feature));
    }

    private function text(string $field): string
    {
        $text = __("guide.articles.{$this->key}.{$field}");

        return is_string($text) ? $text : '';
    }
}
