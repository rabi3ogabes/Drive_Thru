<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants;

use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Restaurants\Pages\CreateRestaurant;
use App\Filament\Admin\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\Admin\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\Admin\Resources\Restaurants\Pages\ViewRestaurant;
use App\Filament\Admin\Resources\Restaurants\RelationManagers\ActivityRelationManager;
use App\Filament\Admin\Resources\Restaurants\RelationManagers\EmployeesRelationManager;
use App\Filament\Admin\Resources\Restaurants\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\Restaurants\Schemas\RestaurantForm;
use App\Filament\Admin\Resources\Restaurants\Schemas\RestaurantInfolist;
use App\Filament\Admin\Resources\Restaurants\Tables\RestaurantsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The platform's clients: one card per restaurant brand, a full client page,
 * and the onboarding wizard.
 */
class RestaurantResource extends Resource
{
    protected static ?string $model = Restaurant::class;

    protected static ?string $slug = 'restaurants';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Clients;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('clients.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('clients.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['subscription.plan'])
            ->withCount(['employees as active_employees_count' => fn (Builder $q) => $q->where('status', EmployeeStatus::Active)]);
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name->en', 'name->ar', 'slug', 'contact_email', 'contact_phone'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Restaurant $record */
        return [
            __('clients.fields.status') => $record->status->getLabel(),
            __('clients.card.plan') => $record->plan()->name ?? '—',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return RestaurantForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RestaurantInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RestaurantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EmployeesRelationManager::class,
            InvoicesRelationManager::class,
            ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestaurants::route('/'),
            'create' => CreateRestaurant::route('/create'),
            'view' => ViewRestaurant::route('/{record}'),
            'edit' => EditRestaurant::route('/{record}/edit'),
        ];
    }
}
