<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PlatformStaff;

use App\Domain\Access\Models\PlatformRole;
use App\Filament\Admin\Actions\StaffActions;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\PlatformStaff\Pages\CreatePlatformStaff;
use App\Filament\Admin\Resources\PlatformStaff\Pages\EditPlatformStaff;
use App\Filament\Admin\Resources\PlatformStaff\Pages\ListPlatformStaff;
use App\Models\User;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The people who run the platform (the super admin and platform staff).
 */
class PlatformStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('team.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('team.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('team.navigation');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('platformRole')
            ->where(fn (Builder $q) => $q->where('is_super_admin', true)->orWhereNotNull('platform_role_id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('team.sections.person'))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('name')->label(__('team.fields.name'))->required()->maxLength(120),
                    TextInput::make('email')
                        ->label(__('team.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(190)
                        ->unique(table: 'users', column: 'email', ignoreRecord: true)
                        ->disabledOn('edit'),
                    TextInput::make('phone')->label(__('team.fields.phone'))->tel()->maxLength(30),
                    Select::make('locale')
                        ->label(__('team.fields.locale'))
                        ->options(collect(Locales::codes())->mapWithKeys(fn (string $c): array => [$c => Locales::native($c)])->all())
                        ->default(app()->getLocale())
                        ->native(false),
                    Select::make('platform_role_id')
                        ->label(__('team.fields.role'))
                        ->options(fn (): array => PlatformRole::query()->get()->mapWithKeys(fn (PlatformRole $r): array => [$r->id => $r->name])->all())
                        ->required(fn (?User $record): bool => ! $record?->isSuperAdmin())
                        ->hidden(fn (?User $record): bool => (bool) $record?->isSuperAdmin())
                        ->native(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('team.fields.name'))
                    ->description(fn (User $record): string => $record->email)
                    ->weight('semibold')
                    ->searchable(['name', 'email']),
                TextColumn::make('role')
                    ->label(__('team.fields.role'))
                    ->state(fn (User $record): string => $record->isSuperAdmin() ? __('team.super_admin') : (string) $record->platformRole?->name)
                    ->badge()
                    ->color(fn (User $record): string => $record->isSuperAdmin() ? 'warning' : 'gray'),
                TextColumn::make('is_active')
                    ->label(__('clients.fields.status'))
                    ->state(fn (User $record): string => match (true) {
                        ! $record->is_active => __('team.status.suspended'),
                        $record->last_login_at === null => __('team.status.invited'),
                        default => __('team.status.active'),
                    })
                    ->badge()
                    ->color(fn (User $record): string => match (true) {
                        ! $record->is_active => 'danger',
                        $record->last_login_at === null => 'info',
                        default => 'success',
                    }),
                IconColumn::make('app_authentication_secret')
                    ->label(__('team.fields.two_factor'))
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->alignCenter(),
                TextColumn::make('last_login_at')->label(__('team.fields.last_seen'))->since()->placeholder(__('clients.team.never'))->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    StaffActions::resendInvitation(),
                    StaffActions::unlockSignIn(),
                    StaffActions::resetTwoFactor(),
                    StaffActions::suspend(),
                    StaffActions::reactivate(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformStaff::route('/'),
            'create' => CreatePlatformStaff::route('/create'),
            'edit' => EditPlatformStaff::route('/{record}/edit'),
        ];
    }
}
