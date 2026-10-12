<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Pages;

use App\Filament\Admin\Resources\Leads\LeadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return LeadResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
