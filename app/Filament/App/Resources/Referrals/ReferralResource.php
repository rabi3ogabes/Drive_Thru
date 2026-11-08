<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Referrals;

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Customers\Models\Customer;
use App\Domain\Referrals\Models\Referral;
use App\Filament\App\Resources\Customers\CustomerResource;
use App\Filament\App\Resources\Referrals\Pages\ListReferrals;
use App\Filament\App\Workspace;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Support\Bidi;
use App\Support\Lang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Friends who joined through a guest's invite: who invited whom, and whether
 * both were rewarded or why not. A record of what happened, never edited.
 */
class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Growth;

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return parent::canAccess() && Workspace::restaurant()->hasFeature(PlanFeature::Loyalty);
    }

    public static function getModelLabel(): string
    {
        return __('referrals.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('referrals.plural');
    }

    public static function table(Table $table): Table
    {
        $tz = fn (): string => Workspace::restaurant()->timezone;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['referrer', 'friend']))
            ->defaultSort('joined_at', 'desc')
            ->columns([
                TextColumn::make('joined_at')->label(__('referrals.columns.joined_at'))->dateTime('d/m/Y H:i')->timezone($tz)->sortable(),
                TextColumn::make('referrer.name')->label(__('referrals.columns.referrer'))
                    ->state(fn (Referral $record): string => self::guest($record->referrer))
                    ->url(fn (Referral $record): ?string => self::guestUrl($record->referrer)),
                TextColumn::make('friend.name')->label(__('referrals.columns.friend'))
                    ->state(fn (Referral $record): string => self::guest($record->friend))
                    ->url(fn (Referral $record): ?string => self::guestUrl($record->friend)),
                TextColumn::make('status')->label(__('referrals.columns.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __('referrals.status.'.$state))
                    ->description(fn (Referral $record): ?string => $record->reason === null ? null : Lang::text('referrals.reasons.'.$record->reason))
                    ->color(fn (string $state): string => match ($state) {
                        Referral::REWARDED => 'success',
                        Referral::PENDING => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('decided_at')->label(__('referrals.columns.decided_at'))->dateTime('d/m/Y')->timezone($tz)->placeholder('—')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('referrals.columns.status'))
                    ->options(collect(Referral::STATUSES)->mapWithKeys(fn (string $status): array => [$status => __('referrals.status.'.$status)])->all()),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserPlus)
            ->emptyStateHeading(__('referrals.empty.heading'))
            ->emptyStateDescription(__('referrals.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferrals::route('/'),
        ];
    }

    /** "Sara · +974 •••• 1234": the number stays masked here. */
    private static function guest(Customer $customer): string
    {
        return trim(($customer->name ?? '').' · '.(string) Bidi::ltr($customer->maskedPhone()), ' ·');
    }

    private static function guestUrl(Customer $customer): ?string
    {
        return CustomerResource::canAccess() ? CustomerResource::getUrl('view', ['record' => $customer]) : null;
    }
}
