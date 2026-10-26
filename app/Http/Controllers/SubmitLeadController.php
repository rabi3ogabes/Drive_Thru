<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Models\Plan;
use App\Domain\Growth\Actions\SubmitLead;
use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Services\Enums\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The public "request access" form. A hidden honeypot field silently drops
 * bots; real requests become leads in the sales pipeline.
 */
final class SubmitLeadController
{
    public function __invoke(Request $request, SubmitLead $submit): RedirectResponse
    {
        if (filled($request->input('company_website'))) {
            return redirect()->to($this->backTo($request).'#request')->with('lead_submitted', true);
        }

        $validated = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'branches_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'plan' => ['nullable', 'string', Rule::exists('plans', 'key')->where('is_public', true)->where('is_active', true)],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', Rule::enum(Service::class)],
            'message' => ['nullable', 'string', 'max:2000'],
        ], attributes: trans('leads.form.attributes'));

        $submit->handle([
            'restaurant_name' => $validated['restaurant_name'],
            'contact_name' => $validated['contact_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'branches_count' => isset($validated['branches_count']) ? (int) $validated['branches_count'] : 1,
            'plan_id' => isset($validated['plan']) ? Plan::query()->where('key', $validated['plan'])->value('id') : null,
            'message' => $validated['message'] ?? null,
            'services' => $validated['services'] ?? [],
        ], LeadSource::Website, app()->getLocale(), $request->ip());

        return redirect()->to($this->backTo($request).'#request')->with('lead_submitted', true);
    }

    /** Back to the website page the form was sent from (never another site). */
    private function backTo(Request $request): string
    {
        $previous = strtok(url()->previous(), '#') ?: route('home');
        $sameSite = parse_url($previous, PHP_URL_HOST) === $request->getHost();
        $path = '/'.ltrim((string) parse_url($previous, PHP_URL_PATH), '/');
        $website = $path === '/' || $path === '/pricing' || str_starts_with($path, '/services/');

        return $sameSite && $website ? $previous : route('home');
    }
}
