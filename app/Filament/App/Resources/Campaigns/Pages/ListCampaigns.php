<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Filament\App\Resources\Campaigns\CampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('campaigns.admin.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('campaigns.admin.add'))->icon('heroicon-m-plus')];
    }
}
