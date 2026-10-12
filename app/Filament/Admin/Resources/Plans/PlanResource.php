<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans;

use App\Domain\Billing\Models\Plan;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Filament\Admin\Resources\Plans\Schemas\PlanForm;
use App\Filament\Admin\Resources\Plans\Tables\PlansTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('plans.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('plans.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
