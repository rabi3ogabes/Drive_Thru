<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Customers\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Crm\GuestExport;
use App\Filament\App\Resources\Customers\CustomerResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGuests extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('crm.guests.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('crm.guests.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => Workspace::can(RestaurantPermission::ExportReports))
                ->action(function (): StreamedResponse {
                    abort_unless(Workspace::can(RestaurantPermission::ExportReports), 403);

                    $query = $this->getFilteredSortedTableQuery();
                    abort_if($query === null, 404);

                    return app(GuestExport::class)->download(
                        $query,
                        Workspace::restaurant(),
                        Workspace::user(),
                        withPhones: Workspace::can(RestaurantPermission::ViewCustomerContacts),
                    );
                }),
        ];
    }
}
