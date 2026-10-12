<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformRoles\Pages;

use App\Filament\Admin\Resources\PlatformRoles\PlatformRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformRoles extends ListRecords
{
    protected static string $resource = PlatformRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-m-plus')];
    }
}
