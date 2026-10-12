<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Screens;

use App\Domain\Branches\Actions\IssuePairingCode;
use App\Domain\Branches\Actions\RevokeDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Device;
use App\Domain\Branches\Models\Station;
use App\Filament\App\Resources\Screens\Pages\ManageScreens;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component as Livewire;
use UnitEnum;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $slug = 'screens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Branches;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('devices.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('devices.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return Workspace::scopeToBranches(parent::getEloquentQuery()->whereNull('revoked_at'), 'branch_id');
    }

    /**
     * @return array<Component>
     */
    public static function formSchema(bool $creating): array
    {
        return [
            Select::make('branch_id')
                ->label(__('devices.fields.branch'))
                ->options(fn (): array => Workspace::branchOptions())
                ->default(fn (): ?int => array_key_first(Workspace::branchOptions()))
                ->required()
                ->native(false)
                ->live()
                ->visible($creating),
            ToggleButtons::make('kind')
                ->label(__('devices.fields.kind'))
                ->options(DeviceKind::class)
                ->default(DeviceKind::Kitchen)
                ->required()
                ->live()
                ->columns(['default' => 1, 'sm' => 2])
                ->visible($creating),
            TextInput::make('name')
                ->label(__('devices.fields.name'))
                ->placeholder(__('devices.fields.name_placeholder'))
                ->required()
                ->maxLength(60),
            Select::make('station_id')
                ->label(__('devices.fields.station'))
                ->helperText(__('devices.fields.station_hint'))
                ->options(fn (Get $get, ?Device $record): array => Station::query()
                    ->where('branch_id', $get('branch_id') ?? $record?->branch_id)
                    ->where('is_active', true)
                    ->orderBy('sort')
                    ->get()
                    ->mapWithKeys(fn (Station $station): array => [$station->id => $station->name])
                    ->all())
                ->placeholder(__('devices.fields.all_stations'))
                ->native(false)
                ->visible(fn (Get $get, ?Device $record): bool => ($get('kind') ?? $record?->kind) === DeviceKind::Kitchen
                    || ($get('kind') ?? null) === DeviceKind::Kitchen->value),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['branch', 'station']))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('name')
                    ->label(__('devices.fields.name'))
                    ->icon(fn (Device $record): string => $record->kind->getIcon())
                    ->description(fn (Device $record): string => $record->kind->getLabel().($record->station ? ' · '.$record->station->name : ''))
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('branch.name')->label(__('devices.fields.branch')),
                TextColumn::make('connection')
                    ->label(__('devices.fields.status'))
                    ->state(fn (Device $record): string => self::status($record))
                    ->formatStateUsing(fn (string $state): string => __("devices.status.{$state}"))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        'waiting' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('last_seen_at')
                    ->label(__('devices.fields.last_seen'))
                    ->since()
                    ->placeholder(__('devices.never')),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label(__('devices.fields.branch'))->options(fn (): array => Workspace::branchOptions()),
                SelectFilter::make('kind')->label(__('devices.fields.kind'))->options(DeviceKind::class),
            ])
            ->recordActions([
                Action::make('newCode')
                    ->label(fn (Device $record): string => $record->isPaired() ? __('devices.actions.repair') : __('devices.actions.new_code'))
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    // Only replacing a working screen needs a second thought; a fresh code is instant.
                    ->requiresConfirmation(fn (Device $record): bool => $record->isPaired())
                    ->modalHidden(fn (Device $record): bool => ! $record->isPaired())
                    ->modalDescription(__('devices.repair_description'))
                    ->authorize(fn (Device $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (Device $record, Livewire $livewire): void {
                        $code = app(IssuePairingCode::class)->handle($record, Workspace::user());

                        if (method_exists($livewire, 'replaceMountedAction')) {
                            $livewire->replaceMountedAction('pairingCode', ['device' => $record->id, 'code' => $code->code]);
                        }
                    }),
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::Large)
                        ->schema(self::formSchema(creating: false)),
                    Action::make('revoke')
                        ->label(__('devices.actions.revoke'))
                        ->icon('heroicon-o-power')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Device $record): string => __('devices.revoke.heading', ['name' => $record->name]))
                        ->modalDescription(__('devices.revoke.description'))
                        ->authorize(fn (Device $record): bool => auth()->user()?->can('delete', $record) ?? false)
                        ->action(function (Device $record): void {
                            app(RevokeDevice::class)->handle($record, Workspace::user());
                            Notification::make()->success()->title(__('devices.notifications.revoked', ['name' => $record->name]))->send();
                        }),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-computer-desktop')
            ->emptyStateHeading(__('devices.empty.heading'))
            ->emptyStateDescription(__('devices.empty.description'));
    }

    public static function status(Device $device): string
    {
        return match (true) {
            $device->isOnline() => 'online',
            $device->isPaired() => 'offline',
            $device->isAwaitingPairing() => 'waiting',
            default => 'unpaired',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScreens::route('/'),
        ];
    }
}
