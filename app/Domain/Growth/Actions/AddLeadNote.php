<?php

declare(strict_types=1);

namespace App\Domain\Growth\Actions;

use App\Domain\Growth\Models\Lead;
use App\Domain\Growth\Models\LeadNote;
use App\Models\User;

final class AddLeadNote
{
    public function handle(Lead $lead, string $body, ?User $author = null): LeadNote
    {
        return $lead->notes()->create([
            'user_id' => $author?->id,
            'body' => trim($body),
        ]);
    }
}
