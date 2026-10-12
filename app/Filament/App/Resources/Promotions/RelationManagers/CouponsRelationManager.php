<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Promotions\RelationManagers;

use App\Domain\Promotions\Actions\GenerateCoupons;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Filament\App\Workspace;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The codes of a promotion: add one, generate many, download them, remove unused ones. */
class CouponsRelationManager extends RelationManager
{
    protected static string $relationship = 'coupons';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('promotions.admin.coupons.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label(__('promotions.admin.coupons.code'))->fontFamily('mono')->copyable()->searchable()->weight('semibold'),
                TextColumn::make('used_count')->label(__('promotions.admin.fields.used'))
                    ->formatStateUsing(fn (Coupon $record): string => $record->usage_limit === null ? number_format($record->used_count) : number_format($record->used_count).' / '.number_format($record->usage_limit)),
                TextColumn::make('per_customer_limit')->label(__('promotions.admin.fields.per_customer_limit'))->placeholder('—'),
                TextColumn::make('expires_at')->label(__('promotions.admin.coupons.expires'))->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('addCode')
                    ->label(__('promotions.admin.coupons.add'))
                    ->icon('heroicon-m-plus')
                    ->schema([
                        TextInput::make('code')->label(__('promotions.admin.coupons.code'))->required()->maxLength(40)->extraInputAttributes(['dir' => 'ltr', 'style' => 'text-transform: uppercase']),
                        ...$this->limitFields(),
                    ])
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(function (array $data, Action $action): void {
                        try {
                            app(GenerateCoupons::class)->single($this->promotion(), (string) $data['code'], $this->int($data['usage_limit'] ?? null), $this->int($data['per_customer_limit'] ?? null), $this->date($data['expires_at'] ?? null), Workspace::user());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->danger()->title(__('promotions.admin.coupons.errors.'.$e->getMessage()))->send();
                            $action->halt();
                        }
                    }),
                Action::make('generate')
                    ->label(__('promotions.admin.coupons.generate'))
                    ->icon('heroicon-m-sparkles')
                    ->color('gray')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('count')->label(__('promotions.admin.coupons.count'))->numeric()->integer()->minValue(1)->maxValue(GenerateCoupons::MAX_BATCH)->default(50)->required(),
                            TextInput::make('prefix')->label(__('promotions.admin.coupons.prefix'))->maxLength(10)->alphaNum(),
                            TextInput::make('length')->label(__('promotions.admin.coupons.length'))->numeric()->integer()->minValue(6)->maxValue(16)->default(8)->required(),
                        ]),
                        ...$this->limitFields(defaultUses: 1),
                    ])
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(function (array $data): void {
                        $codes = app(GenerateCoupons::class)->batch($this->promotion(), (int) $data['count'], (string) ($data['prefix'] ?? ''), (int) $data['length'], $this->int($data['usage_limit'] ?? null), $this->int($data['per_customer_limit'] ?? null), $this->date($data['expires_at'] ?? null), Workspace::user());
                        Notification::make()->success()->title(trans_choice('promotions.admin.coupons.generated', count($codes), ['count' => count($codes)]))->send();
                    }),
                Action::make('download')
                    ->label(__('promotions.admin.coupons.download'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                        $out = fopen('php://output', 'wb');

                        if ($out === false) {
                            return;
                        }

                        fputcsv($out, ['code', 'used', 'usage_limit', 'per_customer_limit', 'expires_at']);
                        foreach ($this->promotion()->coupons()->orderBy('id')->cursor() as $coupon) {
                            fputcsv($out, [$coupon->code, $coupon->used_count, $coupon->usage_limit, $coupon->per_customer_limit, $coupon->expires_at?->toIso8601String()]);
                        }
                        fclose($out);
                    }, 'codes-'.$this->promotion()->id.'.csv', ['Content-Type' => 'text/csv'])),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('promotions.admin.coupons.remove'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Coupon $record): bool => $record->used_count === 0)
                    ->authorize(fn (Coupon $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->action(function (Coupon $record): void {
                        activity()->performedOn($this->promotion())->causedBy(Workspace::user())->event('promotion.code_removed')->withProperties(['code' => $record->code])->log('promotion.code_removed');
                        $record->delete();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateHeading(__('promotions.admin.coupons.empty.heading'))
            ->emptyStateDescription(__('promotions.admin.coupons.empty.description'));
    }

    /**
     * @return list<Grid>
     */
    private function limitFields(?int $defaultUses = null): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                TextInput::make('usage_limit')->label(__('promotions.admin.coupons.uses_per_code'))->numeric()->integer()->minValue(1)->default($defaultUses),
                TextInput::make('per_customer_limit')->label(__('promotions.admin.fields.per_customer_limit'))->numeric()->integer()->minValue(1)->default(1),
                DateTimePicker::make('expires_at')->label(__('promotions.admin.coupons.expires'))->seconds(false)->native(false)->after('now'),
            ]),
        ];
    }

    private function promotion(): Promotion
    {
        /** @var Promotion $promotion */
        $promotion = $this->getOwnerRecord();

        return $promotion;
    }

    private function int(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return filled($value) ? CarbonImmutable::parse((string) $value) : null;
    }
}
