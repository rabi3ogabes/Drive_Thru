<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Domain\Campaigns\Models\Campaign;
use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Filament\App\Workspace;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Saves a draft; it is checked and sent from its own page. */
class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $campaign = Campaign::query()->create([
            'restaurant_id' => Workspace::restaurant()->id,
            'created_by' => Workspace::user()->id,
            ...CampaignResource::attributes($data),
        ]);

        activity()->performedOn($campaign)->causedBy(Workspace::user())->event('campaign.created')
            ->withProperties(['restaurant_id' => $campaign->restaurant_id])->log('campaign.created');

        return $campaign;
    }

    protected function getRedirectUrl(): string
    {
        return CampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
