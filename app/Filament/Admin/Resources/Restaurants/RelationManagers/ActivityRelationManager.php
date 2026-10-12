<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\RelationManagers;

use App\Domain\Access\Enums\PlatformPermission;
use App\Filament\Admin\Resources\Activities\Tables\ActivitiesTable;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Timeline of what happened to this client (onboarding, plan changes,
 * suspensions, "view as owner" visits...).
 */
class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('audit.timeline');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ViewAuditLog);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return ActivitiesTable::configure($table, showSubject: false);
    }
}
