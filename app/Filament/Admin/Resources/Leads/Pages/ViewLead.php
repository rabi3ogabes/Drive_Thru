<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Pages;

use App\Filament\Admin\Actions\LeadActions;
use App\Filament\Admin\Resources\Leads\LeadResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string|Htmlable
    {
        return (string) $this->getRecord()->getAttribute('restaurant_name');
    }

    protected function getHeaderActions(): array
    {
        return [
            LeadActions::convert(),
            LeadActions::changeStatus()->button()->color('gray'),
            ActionGroup::make([
                LeadActions::addNote(),
                LeadActions::assignToMe(),
                LeadActions::whatsapp(),
                EditAction::make(),
            ])->icon('heroicon-m-ellipsis-vertical')->button()->color('gray')->label(__('clients.actions.more')),
        ];
    }
}
