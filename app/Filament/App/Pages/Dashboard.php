<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Tenancy\Models\Restaurant;
use App\Support\Greeting;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string|Htmlable
    {
        return __('dashboard.app.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        $restaurant = Filament::getTenant();

        return Greeting::for(
            auth()->user()?->name,
            $restaurant instanceof Restaurant ? $restaurant->timezone : null,
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        $restaurant = Filament::getTenant();

        return __('dashboard.app.subheading', [
            'restaurant' => $restaurant instanceof Restaurant ? $restaurant->name : '',
        ]);
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.app.title');
    }
}
