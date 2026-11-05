<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuThemes;

use App\Domain\Themes\Models\MenuTheme;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\MenuThemes\Pages\CreateMenuTheme;
use App\Filament\Admin\Resources\MenuThemes\Pages\EditMenuTheme;
use App\Filament\Admin\Resources\MenuThemes\Pages\ListMenuThemes;
use App\Filament\Admin\Resources\MenuThemes\Schemas\MenuThemeForm;
use App\Filament\Admin\Resources\MenuThemes\Tables\MenuThemesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The theme template library restaurants choose from on Menu design.
 */
class MenuThemeResource extends Resource
{
    protected static ?string $model = MenuTheme::class;

    protected static ?string $slug = 'menu-themes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('themes.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('themes.admin.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return MenuThemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenuThemesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuThemes::route('/'),
            'create' => CreateMenuTheme::route('/create'),
            'edit' => EditMenuTheme::route('/{record}/edit'),
        ];
    }
}
