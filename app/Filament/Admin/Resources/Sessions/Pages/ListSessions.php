<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sessions\Pages;

use App\Filament\Admin\Resources\Sessions\SessionResource;
use Filament\Resources\Pages\ListRecords;

class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;
}
