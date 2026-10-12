<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Rewards\Pages;

use App\Domain\Loyalty\Models\Reward;
use App\Filament\App\Resources\Rewards\RewardResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

class ManageRewards extends ManageRecords
{
    protected static string $resource = RewardResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('loyalty.rewards.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addReward')
                ->label(__('loyalty.rewards.add'))
                ->icon('heroicon-m-plus')
                ->modalHeading(__('loyalty.rewards.add'))
                ->modalWidth(Width::TwoExtraLarge)
                ->schema(RewardResource::formSchema())
                ->visible(fn (): bool => Gate::allows('create', Reward::class))
                ->action(function (array $data): void {
                    $reward = Reward::query()->create(['restaurant_id' => Workspace::restaurant()->id, ...RewardResource::attributes($data)]);
                    activity()->performedOn($reward)->causedBy(Workspace::user())->event('loyalty.reward_created')->log('loyalty.reward_created');
                    Notification::make()->success()->title(__('loyalty.rewards.created'))->send();
                }),
        ];
    }
}
