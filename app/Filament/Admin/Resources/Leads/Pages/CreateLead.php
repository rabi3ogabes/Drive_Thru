<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Pages;

use App\Domain\Growth\Enums\LeadStatus;
use App\Filament\Admin\Resources\Leads\LeadResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge($data, [
            'email' => Str::lower(trim((string) $data['email'])),
            'status' => LeadStatus::New,
            'assigned_to' => $data['assigned_to'] ?? auth()->id(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return LeadResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
