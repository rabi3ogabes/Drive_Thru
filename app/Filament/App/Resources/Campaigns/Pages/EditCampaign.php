<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Domain\Campaigns\Actions\RunCampaign;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Filament\App\Workspace;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** Only drafts and scheduled campaigns can be changed; a scheduled one stays scheduled at its new time. */
class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->getRecord();
        $data['when'] = CampaignResource::isForLater($campaign) ? 'later' : 'now';
        $data['message'] = ['ar' => $campaign->getTranslation('message', 'ar', false), 'en' => $campaign->getTranslation('message', 'en', false)];
        $data['segments'] = $campaign->segments ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Campaign $record */
        $attributes = CampaignResource::attributes($data);
        $record->fill($attributes)->save();

        if ($record->status === CampaignStatus::Scheduled) {
            app(RunCampaign::class)->schedule($record, $record->scheduled_at, Workspace::user());
        }

        activity()->performedOn($record)->causedBy(Workspace::user())->event('campaign.updated')
            ->withProperties(['restaurant_id' => $record->restaurant_id])->log('campaign.updated');

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return CampaignResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
