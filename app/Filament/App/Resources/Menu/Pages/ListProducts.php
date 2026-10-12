<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages;

use App\Filament\App\Actions\MenuActions;
use App\Filament\App\Resources\Menu\ProductResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('menu.products.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                MenuActions::import(),
                MenuActions::export(),
                MenuActions::export(template: true),
            ])
                ->label(__('menu.products.actions.excel'))
                ->icon('heroicon-m-table-cells')
                ->button()
                ->color('gray'),
            CreateAction::make()->label(__('menu.products.actions.create'))->icon('heroicon-m-plus'),
        ];
    }
}
