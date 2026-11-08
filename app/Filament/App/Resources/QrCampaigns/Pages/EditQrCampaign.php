<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCampaigns\Pages;

use App\Filament\App\Resources\QrCampaigns\QrCampaignResource;
use Filament\Resources\Pages\EditRecord;

/** The code itself never changes, so printed posters keep working. */
class EditQrCampaign extends EditRecord
{
    protected static string $resource = QrCampaignResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['branch_id'] ?? null)) {
            $data['lane'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return QrCampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
