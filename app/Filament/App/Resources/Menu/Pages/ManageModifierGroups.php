<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages;

use App\Domain\Menu\Models\ModifierGroup;
use App\Filament\App\Resources\Menu\ModifierGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ManageModifierGroups extends ManageRecords
{
    protected static string $resource = ModifierGroupResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('menu.options.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-m-plus')
                ->slideOver()
                ->modalWidth(Width::TwoExtraLarge)
                ->mutateDataUsing(fn (array $data): array => $data + ['sort' => (int) ModifierGroup::query()->max('sort') + 1]),
        ];
    }
}
