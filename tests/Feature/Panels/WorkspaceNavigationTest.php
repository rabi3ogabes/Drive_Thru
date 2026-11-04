<?php

use App\Domain\Tenancy\TenantContext;
use App\Filament\App\WorkspaceNavigationGroup;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

it('shows each workspace group once, with the live screens and the pages filed under it together', function () {
    app()->setLocale('en');
    $restaurant = restaurant(slug: 'masar');
    $this->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    $groups = collect(Filament::getNavigation())->filter(fn (NavigationGroup $group): bool => filled($group->getLabel()));
    $labels = $groups->map(fn (NavigationGroup $group): string => (string) $group->getLabel());

    expect($labels->duplicates()->all())->toBe([]);

    $operations = $groups->first(fn (NavigationGroup $group): bool => $group->getLabel() === WorkspaceNavigationGroup::Operations->getLabel());
    $items = collect($operations?->getItems())->map(fn (NavigationItem $item): string => (string) $item->getLabel());

    expect($items)->toContain(__('ops.screens.live'), __('feedback.admin.plural'), __('support.admin.plural'));
});
