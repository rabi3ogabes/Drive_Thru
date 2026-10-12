<?php

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Domain\Security\Enums\SecurityAlertType;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Tenancy\Enums\RestaurantStatus;
use App\Filament\Admin\AdminNavigationGroup;
use Illuminate\Support\Facades\File;

/*
 * Every literal translation key used in our code must exist in both Arabic
 * and English, so the interface never shows a raw key like "clients.fields.x".
 * Dynamic keys (built from variables) are covered by enum label tests.
 */
it('defines every translation key used in the code, in both languages', function () {
    $groups = collect(File::files(lang_path('en')))->map(fn ($f) => $f->getFilenameWithoutExtension())->all();
    $pattern = '/(?:__|trans_choice|trans)\(\s*[\'"]((?:'.implode('|', array_map('preg_quote', $groups)).')\.[A-Za-z0-9_.\-]+)[\'"]/';

    $keys = collect([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))])
        ->flatMap(function ($file) use ($pattern): array {
            preg_match_all($pattern, (string) file_get_contents($file->getPathname()), $matches);

            return $matches[1];
        })
        ->reject(fn (string $key): bool => str_ends_with($key, '.'))
        ->unique()
        ->values();

    expect($keys->count())->toBeGreaterThan(300);

    $missing = [];

    foreach (['en', 'ar'] as $locale) {
        foreach ($keys as $key) {
            if (! trans()->has($key, $locale, false)) {
                $missing[] = "{$locale}: {$key}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('labels every enum case in both languages', function (string $enum) {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        foreach ($enum::cases() as $case) {
            $label = $case->getLabel();

            expect($label)->not->toContain('.')
                ->and($label)->not->toBeEmpty();

            if (method_exists($case, 'getDescription')) {
                expect($case->getDescription())->not->toMatch('/^[a-z_]+\.[a-z_.]+$/');
            }
        }
    }
})->with([
    PlanFeature::class,
    SubscriptionStatus::class,
    InvoiceStatus::class,
    BillingInterval::class,
    LeadStatus::class,
    LeadSource::class,
    AnnouncementLevel::class,
    AnnouncementAudience::class,
    RestaurantStatus::class,
    EmployeeStatus::class,
    SecurityAlertType::class,
    AdminNavigationGroup::class,
]);
