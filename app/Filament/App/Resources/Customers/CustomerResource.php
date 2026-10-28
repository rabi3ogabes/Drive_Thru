<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Customers;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Crm\Enums\Segment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Models\Tier;
use App\Filament\App\Resources\Customers\Pages\ListGuests;
use App\Filament\App\Resources\Customers\Pages\ViewGuest;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Bidi;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every guest of the restaurant with what the nightly run knows about them:
 * visits, spend, segments, tier and whether they accept offers. Phone
 * numbers are masked unless the employee may see guest contacts.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'guests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('crm.guests.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('crm.guests.plural');
    }

    public static function displayName(Customer $customer): string
    {
        return $customer->name ?? __('crm.guests.unnamed');
    }

    /** The full number for employees who may see contacts, masked for everyone else. */
    public static function phone(Customer $customer): string
    {
        return (string) Bidi::ltr(Workspace::can(RestaurantPermission::ViewCustomerContacts) ? $customer->phone : $customer->maskedPhone());
    }

    public static function loyalty(): bool
    {
        return Workspace::restaurant()->hasFeature(PlanFeature::Loyalty);
    }

    public static function table(Table $table): Table
    {
        $currency = fn (): string => Workspace::restaurant()->currency;
        $contacts = fn (): bool => Workspace::can(RestaurantPermission::ViewCustomerContacts);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tier', 'segments.product']))
            ->defaultSort('last_order_at', 'desc')
            ->searchPlaceholder(__('crm.guests.search'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('crm.guests.fields.guest'))
                    ->weight('semibold')
                    ->formatStateUsing(fn (Customer $record): string => self::displayName($record))
                    ->placeholder(__('crm.guests.unnamed'))
                    ->description(fn (Customer $record): string => self::phone($record))
                    ->searchable(query: function (Builder $query, string $search) use ($contacts): Builder {
                        return $query->where(function (Builder $query) use ($search, $contacts): void {
                            $query->where('name', 'like', '%'.$search.'%');

                            if ($contacts()) {
                                $digits = preg_replace('/\D+/', '', $search);

                                if ($digits !== null && strlen($digits) >= 4) {
                                    $query->orWhere('phone', 'like', '%'.$digits.'%');
                                }
                            }
                        });
                    }),
                TextColumn::make('segments')
                    ->label(__('crm.guests.fields.segments'))
                    ->state(fn (Customer $record): array => $record->segments->pluck('segment')->unique()->values()->all())
                    ->formatStateUsing(fn (Segment $state): string => $state->getLabel())
                    ->color(fn (Segment $state): string => $state->getColor())
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('tier.name')
                    ->label(__('crm.guests.fields.tier'))
                    ->placeholder('—')
                    ->visible(fn (): bool => self::loyalty()),
                TextColumn::make('picked_up_count')
                    ->label(__('crm.guests.fields.orders'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('spent_90d_minor')
                    ->label(__('crm.guests.fields.spent_90d'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state, $currency()))
                    ->sortable(),
                TextColumn::make('last_order_at')
                    ->label(__('crm.guests.fields.last_order'))
                    ->since()
                    ->dateTimeTooltip('Y-m-d H:i', timezone: Workspace::restaurant()->timezone)
                    ->placeholder(__('crm.guests.never'))
                    ->sortable(),
                TextColumn::make('points_balance')
                    ->label(__('crm.guests.fields.points'))
                    ->numeric()
                    ->sortable()
                    ->visible(fn (): bool => self::loyalty()),
                IconColumn::make('offers_consent_at')
                    ->label(__('crm.guests.fields.offers'))
                    ->state(fn (Customer $record): bool => $record->acceptsOffers())
                    ->boolean()
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-minus-small')
                    ->falseColor('gray')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('segment')
                    ->label(__('crm.guests.filters.segment'))
                    ->options(Segment::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('segments', fn (Builder $query) => $query->where('segment', $data['value']))
                        : $query),
                SelectFilter::make('tier_id')
                    ->label(__('crm.guests.filters.tier'))
                    ->options(fn (): array => Tier::query()->orderBy('threshold_points')->get()->mapWithKeys(fn (Tier $tier): array => [$tier->id => $tier->name])->all())
                    ->visible(fn (): bool => self::loyalty()),
                TernaryFilter::make('offers')
                    ->label(__('crm.guests.filters.offers'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('offers_consent_at'),
                        false: fn (Builder $query) => $query->whereNull('offers_consent_at'),
                    ),
            ])
            ->recordUrl(fn (Customer $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading(__('crm.guests.empty.heading'))
            ->emptyStateDescription(__('crm.guests.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'view' => ViewGuest::route('/{record}'),
        ];
    }
}
