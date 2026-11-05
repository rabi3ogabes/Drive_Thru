<?php

use App\Domain\Guide\GuideArticle;
use App\Domain\Guide\GuideCatalog;
use App\Filament\App\Pages\Guide;
use App\Filament\App\Resources\Ratings\Pages\ListRatings;
use App\Filament\App\Resources\Ratings\RatingResource;
use App\Filament\App\Resources\SupportTickets\Pages\ViewSupportTicket;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;

it('has an article for every workspace page and resource', function () {
    $panel = Filament::getPanel('app');
    $classes = [...$panel->getPages(), ...$panel->getResources()];

    $missing = array_values(array_filter($classes, fn (string $class): bool => $class !== Guide::class && GuideCatalog::forPage($class) === null));

    expect($missing)->toBe([]);
});

it('finds the article of a resource from any of its pages', function () {
    expect(GuideCatalog::forPage(ListRatings::class)?->key)->toBe('ratings')
        ->and(GuideCatalog::forPage(RatingResource::class)?->key)->toBe('ratings')
        ->and(GuideCatalog::forPage(ViewSupportTicket::class)?->key)->toBe('support')
        ->and(GuideCatalog::find('support')?->page)->not->toBeNull();
});

it('has every article in Arabic and English with a title, a summary and at least one action', function () {
    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        foreach (GuideCatalog::all() as $article) {
            expect($article->title())->not->toStartWith('guide.')
                ->and($article->summary())->not->toStartWith('guide.')
                ->and($article->actions())->not->toBeEmpty("{$article->key} has no actions in {$locale}");
        }
    }
});

it('has the same keys in both language files', function () {
    $en = array_keys(Arr::dot(require lang_path('en/guide.php')));
    $ar = array_keys(Arr::dot(require lang_path('ar/guide.php')));

    expect(array_values(array_diff($en, $ar)))->toBe([])
        ->and(array_values(array_diff($ar, $en)))->toBe([]);
});

it('covers the branch screens, the guest pages and a start-here path', function () {
    $sections = GuideCatalog::sections();

    expect($sections['screens'] ?? [])->toHaveCount(7)
        ->and($sections['guests'] ?? [])->not->toBeEmpty()
        ->and(GuideCatalog::startHere())->toHaveCount(7)
        ->and(collect(GuideCatalog::startHere())->every(fn (GuideArticle $a): bool => $a->page !== null))->toBeTrue();
});
