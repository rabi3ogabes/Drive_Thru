<?php

declare(strict_types=1);

namespace App\Domain\Growth\Actions;

use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Models\User;
use DomainException;

/**
 * Moves a lead through the pipeline. "Won" only happens through conversion
 * (so every won lead has a real client), and "Lost" needs a reason.
 */
final class UpdateLeadStatus
{
    public function __construct(private readonly AddLeadNote $addNote) {}

    public function handle(Lead $lead, LeadStatus $status, ?User $actor = null, ?string $lostReason = null): Lead
    {
        if ($status === LeadStatus::Won) {
            throw new DomainException(__('leads.errors.won_needs_conversion'));
        }

        if ($lead->status === LeadStatus::Won) {
            throw new DomainException(__('leads.errors.already_won'));
        }

        if ($status === LeadStatus::Lost && blank($lostReason)) {
            throw new DomainException(__('leads.errors.lost_needs_reason'));
        }

        $previous = $lead->status;

        $lead->update([
            'status' => $status,
            'lost_reason' => $status === LeadStatus::Lost ? trim((string) $lostReason) : null,
            'contacted_at' => $lead->contacted_at ?? ($status !== LeadStatus::New ? now() : null),
        ]);

        if ($previous !== $status) {
            $this->addNote->handle($lead, __('leads.notes.status_changed', [
                'from' => $previous->getLabel(),
                'to' => $status->getLabel(),
            ]).($status === LeadStatus::Lost ? ' · '.trim((string) $lostReason) : ''), $actor);
        }

        return $lead;
    }
}
