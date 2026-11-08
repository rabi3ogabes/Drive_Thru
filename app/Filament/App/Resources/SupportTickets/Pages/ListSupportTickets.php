<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SupportTickets\Pages;

use App\Filament\App\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('support.admin.subheading');
    }
}
