<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\StampPrograms\Pages;

use App\Domain\Loyalty\Models\StampProgram;
use App\Filament\App\Resources\StampPrograms\StampProgramResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

class ManageStampPrograms extends ManageRecords
{
    protected static string $resource = StampProgramResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('loyalty.stamps.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStampCard')
                ->label(__('loyalty.stamps.add'))
                ->icon('heroicon-m-plus')
                ->modalHeading(__('loyalty.stamps.add'))
                ->modalWidth(Width::TwoExtraLarge)
                ->schema(StampProgramResource::formSchema())
                ->visible(fn (): bool => Gate::allows('create', StampProgram::class))
                ->action(function (array $data): void {
                    $program = StampProgram::query()->create(['restaurant_id' => Workspace::restaurant()->id, ...StampProgramResource::attributes($data)]);
                    activity()->performedOn($program)->causedBy(Workspace::user())->event('loyalty.stamps_created')->log('loyalty.stamps_created');
                    Notification::make()->success()->title(__('loyalty.stamps.created'))->send();
                }),
        ];
    }
}
