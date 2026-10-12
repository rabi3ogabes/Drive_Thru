<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Support\Greeting;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string|Htmlable
    {
        return __('dashboard.admin.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return Greeting::for(auth()->user()?->name);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('dashboard.admin.subheading');
    }

    /**
     * One column on phones and tablets, two side by side on wide screens.
     *
     * @return array<string, int>
     */
    public function getColumns(): array
    {
        return ['default' => 1, 'xl' => 2];
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.admin.title');
    }
}
