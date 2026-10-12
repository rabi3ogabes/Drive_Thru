<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Filament\Admin\Resources\Plans\PlanResource;
use App\Filament\Concerns\FillsTranslations;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    use FillsTranslations;

    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
