<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Branches;

use App\Domain\Branches\Actions\RevokeDevice;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Filament\App\Actions\BranchActions;
use App\Filament\App\Resources\Branches\Pages\ManageBranches;
use App\Filament\App\Resources\Branches\Schemas\BranchForm;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Branches;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('branches.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('branches.plural');
    }

    /**
     * Branch-level staff only ever see the branches they work in.
     */
    public static function getEloquentQuery(): Builder
    {
        return Workspace::scopeToBranches(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(BranchForm::full());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['restaurant', 'hours', 'closures'])
                ->withCount([
                    'lanes as active_lanes_count' => fn (Builder $q) => $q->where('is_active', true),
                    'devices as active_devices_count' => fn (Builder $q) => $q->whereNull('revoked_at')->whereNotNull('token_hash'),
                ]))
            ->defaultSort('sort')
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                Stack::make([
                    ViewColumn::make('card')
                        ->view('filament.app.branch-card')
                        ->searchable(['name->en', 'name->ar', 'code', 'city->en', 'city->ar']),
                ]),
            ])
            ->recordActions([
                BranchActions::live(),
                EditAction::make()
                    ->slideOver()
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->mutateRecordDataUsing(fn (Branch $record, array $data): array => array_merge($data, [
                        'name' => $record->getTranslations('name'),
                        'city' => $record->getTranslations('city'),
                        'address' => $record->getTranslations('address'),
                    ]))
                    ->mutateDataUsing(function (array $data): array {
                        if (blank($data['code'] ?? null)) {
                            unset($data['code']);
                        }

                        return $data;
                    }),
                BranchActions::hours(),
                ActionGroup::make([
                    BranchActions::openPublicPage(),
                    BranchActions::switchOpen(),
                    DeleteAction::make()
                        ->modalDescription(__('branches.delete.description'))
                        ->after(fn (Branch $record) => Device::query()->where('branch_id', $record->id)->whereNull('revoked_at')->get()
                            ->each(fn (Device $device) => app(RevokeDevice::class)->handle($device, Workspace::user()))),
                ]),
            ])
            ->recordUrl(null)
            ->paginated([12, 24, 48])
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading(__('branches.empty.heading'))
            ->emptyStateDescription(__('branches.empty.description'));
    }

    /**
     * Codes are unique within the restaurant (including deleted branches, so old links never point somewhere new).
     */
    public static function uniqueCodeRule(Unique $rule): Unique
    {
        return $rule->where('restaurant_id', Workspace::restaurant()->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBranches::route('/'),
        ];
    }
}
