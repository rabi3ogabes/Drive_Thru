<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\StampPrograms;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Filament\App\Resources\Rewards\RewardResource;
use App\Filament\App\Resources\StampPrograms\Pages\ManageStampPrograms;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Filament\Support\Bilingual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Stamp cards: "Buy 5 ice creams, the 6th is free". The owner picks what
 * counts, how many stamps fill the card and which reward it gives, and
 * sees the guest's card take shape as they type.
 */
class StampProgramResource extends Resource
{
    protected static ?string $model = StampProgram::class;

    protected static ?string $slug = 'stamp-cards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasFeature(PlanFeature::Loyalty);
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.stamps.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.stamps.plural');
    }

    /**
     * @return array<Component>
     */
    public static function formSchema(): array
    {
        return [
            Html::make(fn (Get $get): HtmlString => new HtmlString(view('filament.app.stamp-card-preview', [
                'name' => (string) ($get('name.'.app()->getLocale()) ?: $get('name.en') ?: __('loyalty.stamps.preview_name')),
                'needed' => max(2, min(20, (int) ($get('stamps_needed') ?: 5))),
                'target' => self::targetLabel($get('target_type'), $get('target_id')),
                'reward' => filled($get('reward_id')) ? Reward::query()->whereKey($get('reward_id'))->first()?->name : null,
                'restaurant' => Workspace::restaurant(),
            ])->render())),
            Bilingual::text('name', __('loyalty.stamps.fields.name'), maxLength: 40),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                ToggleButtons::make('target_type')
                    ->label(__('loyalty.stamps.fields.counts'))
                    ->options(['product' => __('loyalty.stamps.targets.product'), 'category' => __('loyalty.stamps.targets.category')])
                    ->default('product')
                    ->inline()
                    ->required()
                    ->live(),
                Select::make('target_id')
                    ->label(fn (Get $get): string => $get('target_type') === 'category' ? __('loyalty.stamps.targets.category') : __('loyalty.stamps.targets.product'))
                    ->options(fn (Get $get): array => $get('target_type') === 'category'
                        ? MenuCategory::query()->orderBy('sort')->get()->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])->all()
                        : Product::query()->orderBy('sort')->get()->mapWithKeys(fn (Product $p): array => [$p->id => $p->name])->all())
                    ->searchable()
                    ->required()
                    ->live(),
                TextInput::make('stamps_needed')
                    ->label(__('loyalty.stamps.fields.needed'))
                    ->helperText(__('loyalty.stamps.fields.needed_hint'))
                    ->numeric()->integer()->minValue(2)->maxValue(20)->default(5)->required()
                    ->live(debounce: 300),
                Select::make('reward_id')
                    ->label(__('loyalty.stamps.fields.reward'))
                    ->helperText(__('loyalty.stamps.fields.reward_hint'))
                    ->options(fn (): array => Reward::query()->where('is_active', true)->whereNull('archived_at')
                        ->where('type', '!=', RewardType::BonusPoints->value)->orderBy('id')->get()
                        ->mapWithKeys(fn (Reward $r): array => [$r->id => $r->name])->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->createOptionForm(RewardResource::formSchema())
                    ->createOptionUsing(fn (array $data): int => Reward::query()->create(['restaurant_id' => Workspace::restaurant()->id, ...RewardResource::attributes($data)])->id),
                TextInput::make('daily_cap')
                    ->label(__('loyalty.stamps.fields.daily_cap'))
                    ->helperText(__('loyalty.stamps.fields.daily_cap_hint'))
                    ->numeric()->integer()->minValue(1)->maxValue(20)->default(3)->required(),
                TextInput::make('card_expiry_days')
                    ->label(__('loyalty.stamps.fields.expiry'))
                    ->helperText(__('loyalty.stamps.fields.expiry_hint'))
                    ->numeric()->integer()->minValue(7)->maxValue(730)
                    ->suffix(__('loyalty.admin.units.days')),
            ]),
            Toggle::make('is_active')->label(__('loyalty.stamps.fields.active'))->default(true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        return [
            'name' => array_filter((array) $data['name'], 'filled'),
            'target_type' => in_array($data['target_type'] ?? null, StampProgram::TARGETS, true) ? $data['target_type'] : 'product',
            'target_id' => (int) $data['target_id'],
            'stamps_needed' => max(2, min(20, (int) $data['stamps_needed'])),
            'reward_id' => (int) $data['reward_id'],
            'daily_cap' => max(1, min(20, (int) $data['daily_cap'])),
            'card_expiry_days' => filled($data['card_expiry_days'] ?? null) ? (int) $data['card_expiry_days'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('archived_at')->with('reward')
                ->withCount('cards')->withSum('cards', 'completed_count'))
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label(__('loyalty.stamps.fields.name'))->weight('semibold')
                    ->description(fn (StampProgram $record): string => __('loyalty.stamps.summary', [
                        'count' => $record->stamps_needed,
                        'item' => $record->targetName(),
                        'reward' => $record->reward->name ?? '',
                    ]))->searchable(),
                TextColumn::make('cards_count')->label(__('loyalty.stamps.cards'))->numeric(),
                TextColumn::make('cards_sum_completed_count')->label(__('loyalty.stamps.completed'))->numeric()->placeholder('0'),
                ToggleColumn::make('is_active')->label(__('loyalty.stamps.fields.active'))
                    ->disabled(fn (): bool => ! (auth()->user()?->can('create', StampProgram::class) ?? false)),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::TwoExtraLarge)
                        ->fillForm(fn (StampProgram $record): array => [
                            ...$record->only(['target_type', 'target_id', 'stamps_needed', 'reward_id', 'daily_cap', 'card_expiry_days', 'is_active']),
                            'name' => $record->getTranslations('name'),
                        ])
                        ->schema(self::formSchema())
                        ->using(function (StampProgram $record, array $data): StampProgram {
                            $record->update(self::attributes($data));
                            activity()->performedOn($record)->causedBy(Workspace::user())->event('loyalty.stamps_updated')->log('loyalty.stamps_updated');

                            return $record;
                        }),
                    Action::make('archive')
                        ->label(__('loyalty.stamps.archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('loyalty.stamps.archive_description'))
                        ->authorize(fn (StampProgram $record): bool => auth()->user()?->can('delete', $record) ?? false)
                        ->action(function (StampProgram $record): void {
                            $record->forceFill(['archived_at' => now(), 'is_active' => false])->save();
                            activity()->performedOn($record)->causedBy(Workspace::user())->event('loyalty.stamps_archived')->log('loyalty.stamps_archived');
                            Notification::make()->success()->title(__('loyalty.stamps.archived'))->send();
                        }),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading(__('loyalty.stamps.empty.heading'))
            ->emptyStateDescription(__('loyalty.stamps.empty.description'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageStampPrograms::route('/')];
    }

    private static function targetLabel(mixed $type, mixed $id): ?string
    {
        if (! filled($id)) {
            return null;
        }

        return $type === 'category'
            ? MenuCategory::query()->whereKey($id)->first()?->name
            : Product::query()->whereKey($id)->first()?->name;
    }
}
