<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformStaff\Pages;

use App\Domain\Access\Actions\InvitePlatformStaff;
use App\Domain\Access\Models\PlatformRole;
use App\Filament\Admin\Actions\ClientActions;
use App\Filament\Admin\Resources\PlatformStaff\PlatformStaffResource;
use App\Models\User;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePlatformStaff extends CreateRecord
{
    protected static string $resource = PlatformStaffResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('team.actions.invite');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $admin */
        $admin = auth()->user();

        try {
            $result = app(InvitePlatformStaff::class)->handle(
                (string) $data['name'],
                (string) $data['email'],
                PlatformRole::query()->findOrFail((int) $data['platform_role_id']),
                $admin,
                $data['locale'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['data.email' => $exception->getMessage()]);
        }

        if (filled($data['phone'] ?? null)) {
            $result['user']->forceFill(['phone' => $data['phone']])->save();
        }

        ClientActions::notifyInvitationLink($result['url'], $data['phone'] ?? null, config('app.name'));

        return $result['user'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return PlatformStaffResource::getUrl('index');
    }
}
