<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Filament\Admin\Resources\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;
}
