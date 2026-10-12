<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages;

use App\Domain\Menu\Models\MenuCategory;
use App\Filament\App\Resources\Menu\MenuCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageMenuCategories extends ManageRecords
{
    protected static string $resource = MenuCategoryResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('menu.sections.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-m-plus')
                ->mutateDataUsing(fn (array $data): array => $data + ['sort' => (int) MenuCategory::query()->max('sort') + 1]),
        ];
    }
}
