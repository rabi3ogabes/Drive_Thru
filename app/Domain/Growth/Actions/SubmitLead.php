<?php

declare(strict_types=1);

namespace App\Domain\Growth\Actions;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Domain\Growth\Notifications\LeadAcknowledgement;
use App\Domain\Growth\Notifications\NewLeadReceived;
use App\Domain\Services\Enums\Service;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Records a request to join. A repeat submission from the same email within
 * 24 hours updates the open lead instead of creating a duplicate. Website
 * leads get an acknowledgement email; the sales team gets a bell alert.
 */
final class SubmitLead
{
    /**
     * @param  array{restaurant_name: string, contact_name: string, email: string, phone?: ?string, branches_count?: ?int, plan_id?: ?int, message?: ?string}  $data
     */
    public function handle(array $data, LeadSource $source = LeadSource::Website, ?string $locale = null, ?string $ip = null): Lead
    {
        $email = Str::lower(trim($data['email']));

        $attributes = [
            'restaurant_name' => trim($data['restaurant_name']),
            'contact_name' => trim($data['contact_name']),
            'email' => $email,
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'branches_count' => max(1, min(500, (int) ($data['branches_count'] ?? 1))),
            'plan_id' => $data['plan_id'] ?? null,
            'services' => array_values(array_intersect(array_column(Service::cases(), 'value'), (array) ($data['services'] ?? []))) ?: null,
            'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
            'locale' => $locale ?? app()->getLocale(),
            'source' => $source,
            'ip_hash' => $ip !== null ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
        ];

        $existing = Lead::query()
            ->where('email', $email)
            ->open()
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing;
        }

        $lead = Lead::create($attributes + ['status' => LeadStatus::New]);

        $team = User::query()->where('is_active', true)->with('platformRole')->get()
            ->filter(fn (User $user): bool => $user->hasPlatformPermission(PlatformPermission::ManageLeads));

        Notification::send($team, new NewLeadReceived($lead));

        if ($source === LeadSource::Website) {
            Notification::route('mail', $email)->notify((new LeadAcknowledgement($lead))->locale($lead->locale));
        }

        return $lead;
    }
}
