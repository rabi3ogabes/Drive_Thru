<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Positions;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Access\Models\Position;
use App\Domain\Staff\Actions\SavePosition;
use App\Domain\Staff\StaffGuard;
use App\Filament\App\Resources\Positions\Pages\ManagePositions;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use App\Filament\Support\PermissionMatrix;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Team;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('positions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('positions.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('positions.sections.identity'))->compact()->schema([
                Bilingual::text('name', __('positions.fields.name'), maxLength: 60),
                Bilingual::textarea('description', __('positions.fields.description'), maxLength: 300),
            ]),
            Section::make(__('positions.sections.permissions'))
                ->description(__('positions.sections.permissions_hint'))
                ->schema(PermissionMatrix::sections(RestaurantPermission::class, self::lockedPermission(...))),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('employees'))
            ->defaultSort('sort')
            ->reorderable('sort', fn (): bool => Workspace::can(RestaurantPermission::ManageStaff))
            ->columns([
                TextColumn::make('name')
                    ->label(__('positions.fields.name'))
                    ->description(fn (Position $record): ?string => $record->description)
                    ->weight('semibold')
                    ->wrap()
                    ->extraAttributes(['class' => 'min-w-64 max-w-xl'])
                    ->searchable(['name->en', 'name->ar']),
                TextColumn::make('permissions')
                    ->label(__('positions.fields.permissions'))
                    ->state(fn (Position $record): string => trans_choice('positions.permissions_count', $record->permissions->count(), ['count' => $record->permissions->count()]))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('employees_count')->label(__('positions.fields.people'))->numeric()->alignCenter(),
                TextColumn::make('is_system')
                    ->label('')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('positions.built_in') : __('positions.custom'))
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'info' : 'warning'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::FourExtraLarge)
                    ->fillForm(fn (Position $record): array => self::fill($record))
                    ->using(fn (Position $record, array $data, EditAction $action): Position => self::save($data, $action, $record))
                    ->successNotificationTitle(__('positions.notifications.saved')),
                ActionGroup::make([
                    Action::make('restore')
                        ->label(__('positions.actions.restore'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->requiresConfirmation()
                        ->modalDescription(__('positions.restore_description'))
                        ->visible(fn (Position $record): bool => $record->is_system)
                        ->authorize(fn (Position $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->action(function (Position $record): void {
                            try {
                                app(SavePosition::class)->reset($record, Workspace::user());
                                Notification::make()->success()->title(__('positions.notifications.restored'))->send();
                            } catch (AuthorizationException $e) {
                                Notification::make()->danger()->title($e->getMessage())->send();
                            }
                        }),
                    DeleteAction::make()
                        ->modalDescription(__('positions.delete_description'))
                        ->using(function (Position $record, DeleteAction $action): bool {
                            try {
                                app(SavePosition::class)->delete($record, Workspace::user());

                                return true;
                            } catch (ValidationException|AuthorizationException $e) {
                                Notification::make()->warning()->title($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())->send();
                                throw new Halt;
                            }
                        }),
                ]),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading(__('positions.empty'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function fill(Position $record): array
    {
        return [
            'name' => $record->getTranslations('name'),
            'description' => $record->getTranslations('description'),
            'permission_groups' => PermissionMatrix::toForm(RestaurantPermission::class, $record->permissions),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data, Action $action, ?Position $record = null): Position
    {
        try {
            return app(SavePosition::class)->handle(
                Workspace::restaurant(),
                Workspace::user(),
                (array) ($data['name'] ?? []),
                (array) ($data['description'] ?? []),
                PermissionMatrix::fromForm(RestaurantPermission::class, (array) ($data['permission_groups'] ?? [])),
                $record,
            );
        } catch (ValidationException|AuthorizationException $e) {
            Notification::make()->danger()->title($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())->send();
            throw new Halt;
        }
    }

    public static function lockedPermission(string $value): bool
    {
        $permission = RestaurantPermission::tryFrom($value);

        return $permission === null || ! app(StaffGuard::class)->canGrant(Workspace::user(), Workspace::restaurant(), [$permission]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePositions::route('/'),
        ];
    }
}
