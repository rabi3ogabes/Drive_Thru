<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Pages;

use App\Domain\Billing\Models\Plan;
use App\Domain\Tenancy\Actions\OnboardClient;
use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use App\Filament\Admin\Resources\Restaurants\Schemas\OnboardingSteps;
use App\Models\User;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * "New client" wizard: brand → owner → plan → review. One click opens the
 * restaurant, its owner, the subscription and sends the owner's invitation.
 */
class CreateRestaurant extends CreateRecord
{
    use HasWizard;

    protected static string $resource = RestaurantResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('clients.wizard.title');
    }

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return OnboardingSteps::make();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $result = app(OnboardClient::class)->handle(OnboardingSteps::toData($data), $actor);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['data.owner_email' => $exception->getMessage()]);
        }

        if ($result['invitation_url'] !== null) {
            session()->flash('client_invitation_url', $result['invitation_url']);
        }

        return $result['restaurant'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('clients.wizard.created'))
            ->body(session('client_invitation_url') ? __('clients.wizard.created_invited') : __('clients.wizard.created_existing_owner'));
    }

    protected function getRedirectUrl(): string
    {
        return RestaurantResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    public static function defaultPlanId(): ?int
    {
        return Plan::query()->available()->where('is_highlighted', true)->value('id')
            ?? Plan::query()->available()->value('id');
    }
}
