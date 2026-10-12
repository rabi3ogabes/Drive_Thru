<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformRoles\Pages;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Filament\Admin\Resources\PlatformRoles\PlatformRoleResource;
use App\Filament\Concerns\FillsTranslations;
use App\Filament\Support\PermissionMatrix;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformRole extends EditRecord
{
    use FillsTranslations;

    protected static string $resource = PlatformRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function afterTranslationsFilled(array $data): array
    {
        /** @var PlatformRole $role */
        $role = $this->getRecord();
        $data['permission_groups'] = PermissionMatrix::toForm(PlatformPermission::class, $role->permissions);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
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
