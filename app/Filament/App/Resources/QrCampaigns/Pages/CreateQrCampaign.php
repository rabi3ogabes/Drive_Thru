<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCampaigns\Pages;

use App\Domain\Campaigns\Models\QrCampaign;
use App\Filament\App\Resources\QrCampaigns\QrCampaignResource;
use App\Filament\App\Workspace;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Creates the code, then opens its page with the posters to download. */
class CreateQrCampaign extends CreateRecord
{
    protected static string $resource = QrCampaignResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $campaign = QrCampaign::query()->create([
            ...$data,
            'restaurant_id' => Workspace::restaurant()->id,
            'created_by' => Workspace::user()->id,
            'lane' => filled($data['branch_id'] ?? null) ? ($data['lane'] ?? null) : null,
        ]);

        activity()->performedOn($campaign)->causedBy(Workspace::user())->event('qr_campaign.created')
            ->withProperties(['restaurant_id' => $campaign->restaurant_id])->log('qr_campaign.created');

        return $campaign;
    }

    protected function getRedirectUrl(): string
    {
        return QrCampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
