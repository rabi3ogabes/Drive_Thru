<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformStaff\Pages;

use App\Filament\Admin\Resources\PlatformStaff\PlatformStaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformStaff extends ListRecords
{
    protected static string $resource = PlatformStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('team.actions.invite'))->icon('heroicon-m-user-plus')];
    }
}
