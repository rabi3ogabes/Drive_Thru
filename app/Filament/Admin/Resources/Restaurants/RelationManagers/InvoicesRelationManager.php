<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\RelationManagers;

use App\Domain\Access\Enums\PlatformPermission;
use App\Filament\Admin\Actions\InvoiceActions;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('billing.invoices.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ManageBilling);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return InvoicesTable::configure($table, showClient: false)
            ->recordActions([
                InvoiceActions::download(),
                InvoiceActions::markPaid(),
                InvoiceActions::void(),
            ]);
    }
}
