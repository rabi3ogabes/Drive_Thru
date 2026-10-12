<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Access\Actions\InviteUser;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Tenancy\Data\OnboardClientData;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens a client account in one step: the restaurant (with its built-in
 * positions), its owner, the subscription and the owner's invitation.
 * Used by the "New client" wizard and by lead conversion.
 */
final class OnboardClient
{
    public function __construct(
        private readonly CreateRestaurant $createRestaurant,
        private readonly StartSubscription $startSubscription,
        private readonly InviteUser $inviteUser,
    ) {}

    /**
     * @return array{restaurant: Restaurant, owner: User, invitation_url: ?string}
     */
    public function handle(OnboardClientData $data, ?User $actor = null): array
    {
        $email = Str::lower(trim($data->ownerEmail));

        /** @var array{restaurant: Restaurant, owner: User, needs_invitation: bool} $result */
        $result = DB::transaction(function () use ($data, $email): array {
            $owner = User::query()->where('email', $email)->first();
            $isNewOwner = $owner === null;

            if ($owner !== null && $owner->isPlatformStaff()) {
                throw new DomainException(__('clients.errors.owner_is_platform_staff'));
            }

            $owner ??= User::create([
                'name' => trim($data->ownerName),
                'email' => $email,
                'phone' => $data->ownerPhone,
                'password' => Str::password(40),
                'locale' => $data->locale,
            ]);

            $restaurant = $this->createRestaurant->handle(
                $data->name,
                owner: $owner,
                slug: $data->slug,
                timezone: $data->timezone,
                defaultLocale: $data->locale,
                currency: $data->plan->currency,
            );

            $restaurant->forceFill([
                'contact_email' => $data->contactEmail ?? $email,
                'contact_phone' => $data->contactPhone ?? $data->ownerPhone,
            ])->save();

            $this->startSubscription->handle(
                $restaurant,
                $data->plan,
                quantity: $data->branches,
                trialDays: $data->trialDays,
                unitPriceMinor: $data->unitPriceMinor,
            );

            return ['restaurant' => $restaurant, 'owner' => $owner, 'needs_invitation' => $isNewOwner];
        });

        activity()
            ->performedOn($result['restaurant'])
            ->causedBy($actor)
            ->event('client.onboarded')
            ->withProperties(['plan' => $data->plan->key, 'branches' => $data->branches])
            ->log('Client onboarded');

        $url = $result['needs_invitation']
            ? $this->inviteUser->handle($result['owner'], $actor, $data->sendInvitation, $result['restaurant']->name)
            : null;

        return ['restaurant' => $result['restaurant'], 'owner' => $result['owner'], 'invitation_url' => $url];
    }
}
