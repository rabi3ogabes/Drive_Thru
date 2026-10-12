<?php

declare(strict_types=1);

namespace App\Domain\Growth\Actions;

use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Domain\Tenancy\Actions\OnboardClient;
use App\Domain\Tenancy\Data\OnboardClientData;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * "Convert to client": opens the client account from the lead and closes the
 * lead as won, linked to the new restaurant.
 */
final class ConvertLead
{
    public function __construct(
        private readonly OnboardClient $onboard,
        private readonly AddLeadNote $addNote,
    ) {}

    /**
     * @return array{restaurant: Restaurant, owner: User, invitation_url: ?string}
     */
    public function handle(Lead $lead, OnboardClientData $data, ?User $actor = null): array
    {
        if ($lead->status === LeadStatus::Won) {
            throw new DomainException(__('leads.errors.already_won'));
        }

        return DB::transaction(function () use ($lead, $data, $actor): array {
            $result = $this->onboard->handle($data, $actor);

            $lead->update([
                'status' => LeadStatus::Won,
                'converted_restaurant_id' => $result['restaurant']->id,
                'converted_at' => now(),
                'contacted_at' => $lead->contacted_at ?? now(),
                'lost_reason' => null,
            ]);

            $this->addNote->handle($lead, __('leads.notes.converted', ['restaurant' => $result['restaurant']->name]), $actor);

            return $result;
        });
    }
}
