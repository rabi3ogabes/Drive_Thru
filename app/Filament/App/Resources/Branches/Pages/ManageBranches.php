<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Branches\Pages;

use App\Domain\Billing\Exceptions\PlanLimitReached;
use App\Domain\Billing\PlanLimits;
use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Filament\App\Resources\Branches\BranchResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

class ManageBranches extends ManageRecords
{
    protected static string $resource = BranchResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $limits = app(PlanLimits::class);
        $restaurant = Workspace::restaurant();
        $limit = $limits->limit($restaurant, PlanLimits::BRANCHES);

        if ($limit === null || ! Gate::allows('create', Branch::class)) {
            return __('branches.subheading');
        }

        return __('billing.limits.branches.usage', ['used' => $limits->used($restaurant, PlanLimits::BRANCHES), 'limit' => $limit]);
    }

    protected function getHeaderActions(): array
    {
        $atLimit = ! app(PlanLimits::class)->allows(Workspace::restaurant(), PlanLimits::BRANCHES);

        return [
            Action::make('limitReached')
                ->label(__('branches.actions.add'))
                ->icon('heroicon-m-plus')
                ->color('gray')
                ->visible(fn (): bool => $atLimit && Gate::allows('create', Branch::class))
                ->modalIcon('heroicon-o-arrow-trending-up')
                ->modalHeading(__('branches.limit.heading'))
                ->modalDescription(fn (): string => $this->limitMessage())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('branches.limit.ok')),
            CreateAction::make()
                ->label(__('branches.actions.add'))
                ->icon('heroicon-m-plus')
                ->slideOver()
                ->modalWidth(Width::ThreeExtraLarge)
                ->visible(! $atLimit)
                ->using(function (array $data, CreateAction $action): Branch {
                    try {
                        return app(CreateBranch::class)->handle(Workspace::restaurant(), $data);
                    } catch (PlanLimitReached $e) {
                        Notification::make()->warning()->title(__('branches.limit.heading'))->body($e->getMessage())->persistent()->send();
                        throw new Halt;
                    }
                })
                ->successNotificationTitle(__('branches.notifications.created')),
        ];
    }

    private function limitMessage(): string
    {
        $restaurant = Workspace::restaurant();
        $limit = (int) app(PlanLimits::class)->limit($restaurant, PlanLimits::BRANCHES);

        return PlanLimitReached::friendly(PlanLimits::BRANCHES, $limit, $restaurant->plan()?->name).' '.__('branches.limit.contact');
    }
}
