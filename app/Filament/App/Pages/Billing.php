<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\App\WorkspaceNavigationGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * The owner's subscription: plan, trial or next invoice, how to pay, and
 * every invoice as a downloadable PDF.
 */
class Billing extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.app.pages.billing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = WorkspaceNavigationGroup::Restaurant;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $restaurant = Filament::getTenant();

        return $user instanceof User
            && $restaurant instanceof Restaurant
            && $user->hasRestaurantPermission($restaurant, RestaurantPermission::ManageBilling);
    }

    public static function getNavigationLabel(): string
    {
        return __('billing.owner.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing.owner.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing.owner.subheading');
    }

    public function restaurant(): Restaurant
    {
        /** @var Restaurant $restaurant */
        $restaurant = Filament::getTenant();

        return $restaurant->loadMissing('subscription.plan');
    }

    public function bankDetails(): ?string
    {
        return app(PlatformSettings::class)->string('billing.bank_details');
    }

    public function billingEmail(): ?string
    {
        return app(PlatformSettings::class)->string('billing.email');
    }

    public function table(Table $table): Table
    {
        return InvoicesTable::configure($table, showClient: false)
            ->query(Invoice::query()->where('restaurant_id', $this->restaurant()->id))
            ->recordActions([
                Action::make('downloadPdf')
                    ->label(__('billing.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.pdf', $record), shouldOpenInNewTab: true),
            ]);
    }
}
