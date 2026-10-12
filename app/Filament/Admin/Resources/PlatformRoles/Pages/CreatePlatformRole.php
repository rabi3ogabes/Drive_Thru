<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformRoles\Pages;

use App\Domain\Access\Enums\PlatformPermission;
use App\Filament\Admin\Resources\PlatformRoles\PlatformRoleResource;
use App\Filament\Support\PermissionMatrix;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformRole extends CreateRecord
{
    protected static string $resource = PlatformRoleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['permissions'] = PermissionMatrix::fromForm(PlatformPermission::class, (array) ($data['permission_groups'] ?? []));
        unset($data['permission_groups']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return PlatformRoleResource::getUrl('index');
    }
}
