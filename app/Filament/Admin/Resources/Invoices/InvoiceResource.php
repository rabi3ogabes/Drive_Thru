<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Tenancy\Scopes\RestaurantScope;
use App\Filament\Admin\Actions\InvoiceActions;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Billing;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getModelLabel(): string
    {
        return __('billing.invoices.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing.invoices.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(RestaurantScope::class);
    }

    public static function getNavigationBadge(): ?string
    {
        $overdue = Invoice::withoutTenancy()->where('status', InvoiceStatus::Open)->where('due_at', '<', now())->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('billing.overdue');
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table)->recordActions([
            InvoiceActions::download(),
            InvoiceActions::markPaid(),
            InvoiceActions::void(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }
}
