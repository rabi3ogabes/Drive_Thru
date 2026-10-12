<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Pages;

use App\Domain\Tenancy\Models\Restaurant;
use App\Filament\Admin\Actions\ClientActions;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRestaurant extends ViewRecord
{
    protected static string $resource = RestaurantResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Right after onboarding: offer the owner's invitation link for sharing.
        if (is_string($url = session('client_invitation_url'))) {
            /** @var Restaurant $restaurant */
            $restaurant = $this->getRecord();
            ClientActions::notifyInvitationLink($url, $restaurant->owner()->phone ?? $restaurant->contact_phone, $restaurant->name);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->getAttribute('name');
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->getRecord();
        $other = app()->getLocale() === 'ar' ? 'en' : 'ar';

        return $restaurant->getTranslation('name', $other, false);
    }

    protected function getHeaderActions(): array
    {
        return [
            ClientActions::viewAsOwner(),
            EditAction::make(),
            ActionGroup::make([
                ClientActions::changePlan(),
                ClientActions::issueInvoice(),
                ClientActions::resendInvitation(),
                ClientActions::suspend(),
                ClientActions::reactivate(),
            ])
                ->label(__('clients.actions.more'))
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
