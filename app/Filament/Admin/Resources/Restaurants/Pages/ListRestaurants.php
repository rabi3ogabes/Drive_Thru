<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Pages;

use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRestaurants extends ListRecords
{
    protected static string $resource = RestaurantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('clients.actions.new'))->icon('heroicon-m-plus'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Restaurant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = ['all' => Tab::make(__('clients.tabs.all'))->badge((string) $counts->sum())];

        foreach (RestaurantStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge((string) ($counts[$status->value] ?? 0))
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }
}
