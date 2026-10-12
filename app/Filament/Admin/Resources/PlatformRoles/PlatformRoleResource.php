<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformRoles;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\PlatformRoles\Pages\CreatePlatformRole;
use App\Filament\Admin\Resources\PlatformRoles\Pages\EditPlatformRole;
use App\Filament\Admin\Resources\PlatformRoles\Pages\ListPlatformRoles;
use App\Filament\Support\Bilingual;
use App\Filament\Support\PermissionMatrix;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlatformRoleResource extends Resource
{
    protected static ?string $model = PlatformRole::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('team.roles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('team.roles.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('team.roles.sections.identity'))->schema([
                Bilingual::text('name', __('team.roles.fields.name'), maxLength: 60),
                Bilingual::textarea('description', __('team.roles.fields.description'), maxLength: 300),
            ]),
            Section::make(__('team.roles.sections.permissions'))
                ->description(__('team.roles.sections.permissions_hint'))
                ->schema(PermissionMatrix::sections(PlatformPermission::class)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('users'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('team.roles.fields.name'))
                    ->description(fn (PlatformRole $record): ?string => $record->description)
                    ->weight('semibold')
                    ->searchable(['name->en', 'name->ar']),
                TextColumn::make('permissions')
                    ->label(__('team.roles.fields.permissions'))
                    ->state(fn (PlatformRole $record): string => trans_choice('team.roles.permissions_count', $record->permissions->count(), ['count' => $record->permissions->count()]))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('users_count')->label(__('team.roles.fields.members'))->numeric()->alignCenter(),
                TextColumn::make('is_system')
                    ->label('')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('team.roles.system') : '')
                    ->badge()
                    ->color('info'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('team.roles.empty'))
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformRoles::route('/'),
            'create' => CreatePlatformRole::route('/create'),
            'edit' => EditPlatformRole::route('/{record}/edit'),
        ];
    }
}
