<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Announcements\Pages;

use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-m-plus')
                ->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
        ];
    }
}
