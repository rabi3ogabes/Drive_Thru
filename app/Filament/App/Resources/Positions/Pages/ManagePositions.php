<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Positions\Pages;

use App\Domain\Access\Models\Position;
use App\Filament\App\Resources\Positions\PositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ManagePositions extends ManageRecords
{
    protected static string $resource = PositionResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('positions.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('positions.actions.create'))
                ->icon('heroicon-m-plus')
                ->modalWidth(Width::FourExtraLarge)
                ->using(fn (array $data, CreateAction $action): Position => PositionResource::save($data, $action))
                ->successNotificationTitle(__('positions.notifications.saved')),
        ];
    }
}
