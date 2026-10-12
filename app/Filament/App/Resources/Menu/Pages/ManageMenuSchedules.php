<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages;

use App\Filament\App\Resources\Menu\MenuScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageMenuSchedules extends ManageRecords
{
    protected static string $resource = MenuScheduleResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('menu.schedules.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-m-plus')];
    }
}
