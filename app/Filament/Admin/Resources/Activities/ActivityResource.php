<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities;

use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Tables\ActivitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'audit-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Security;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('audit.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('audit.plural');
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
