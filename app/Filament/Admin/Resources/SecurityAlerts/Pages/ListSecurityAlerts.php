<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SecurityAlerts\Pages;

use App\Domain\Security\Models\SecurityAlert;
use App\Filament\Admin\Resources\SecurityAlerts\SecurityAlertResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSecurityAlerts extends ListRecords
{
    protected static string $resource = SecurityAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markEverythingRead')
                ->label(__('security.center.mark_all_read'))
                ->icon('heroicon-m-check')
                ->color('gray')
                ->visible(fn (): bool => SecurityAlert::unread()->exists())
                ->action(fn () => SecurityAlert::unread()->update(['read_at' => now()])),
        ];
    }
}
