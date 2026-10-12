<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformStaff\Pages;

use App\Filament\Admin\Resources\PlatformStaff\PlatformStaffResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPlatformStaff extends EditRecord
{
    protected static string $resource = PlatformStaffResource::class;

    /**
     * Role changes go through forceFill: platform_role_id is deliberately not mass assignable.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $record->fill(collect($data)->only(['name', 'phone', 'locale'])->all());

        if (! $record->isSuperAdmin() && array_key_exists('platform_role_id', $data)) {
            $record->forceFill(['platform_role_id' => $data['platform_role_id']]);
        }

        $record->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return PlatformStaffResource::getUrl('index');
    }
}
