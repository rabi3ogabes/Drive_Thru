<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCampaigns\Pages;

use App\Filament\App\Resources\QrCampaigns\QrCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListQrCampaigns extends ListRecords
{
    protected static string $resource = QrCampaignResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('qr.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('qr.add'))->icon('heroicon-m-plus')];
    }
}
