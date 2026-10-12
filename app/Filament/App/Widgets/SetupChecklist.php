<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Tenancy\SetupChecklist as Checklist;
use App\Filament\App\Pages\LinksCenter;
use App\Filament\App\Pages\RestaurantSettings;
use App\Filament\App\Resources\Branches\BranchResource;
use App\Filament\App\Resources\Screens\DeviceResource;
use App\Filament\App\Resources\Team\EmployeeResource;
use App\Filament\App\Workspace;
use Filament\Widgets\Widget;

/**
 * "Get ready to go live": the owner's setup steps with a progress ring.
 * Each step links straight to where it gets done. Hidden once dismissed.
 */
class SetupChecklist extends Widget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.setup-checklist';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (! Workspace::can(RestaurantPermission::ManageSettings)) {
            return false;
        }

        return ! app(Checklist::class)->isDismissed(Workspace::restaurant());
    }

    public function dismiss(): void
    {
        abort_unless(static::canView(), 403);

        app(Checklist::class)->dismiss(Workspace::restaurant());
    }

    /**
     * @return array{progress: int, complete: bool, steps: list<array{key: string, done: bool, url: string}>}
     */
    protected function getViewData(): array
    {
        $checklist = app(Checklist::class);
        $restaurant = Workspace::restaurant();

        $urls = [
            'brand' => RestaurantSettings::getUrl(),
            'branch' => BranchResource::getUrl(),
            'hours' => BranchResource::getUrl(),
            'team' => EmployeeResource::getUrl(),
            'screen' => DeviceResource::getUrl(),
            'qr' => LinksCenter::getUrl(),
        ];

        $steps = [];

        foreach ($checklist->steps($restaurant) as $key => $done) {
            $steps[] = ['key' => $key, 'done' => $done, 'url' => $urls[$key]];
        }

        return [
            'progress' => $checklist->progress($restaurant),
            'complete' => $checklist->isComplete($restaurant),
            'steps' => $steps,
        ];
    }
}
