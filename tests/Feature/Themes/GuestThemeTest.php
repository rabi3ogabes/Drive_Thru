<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeCss;
use App\Domain\Themes\ThemePreview;
use App\Domain\Themes\ThemeResolver;
use Illuminate\Support\Facades\DB;

function themedShop(string $template = 'olive-grove', string $slug = 'masar'): Restaurant
{
    $restaurant = restaurant(slug: $slug);
    Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);
    ThemeResolver::save($restaurant, $template, MenuTheme::query()->where('key', $template)->firstOrFail()->spec());

    return $restaurant->fresh();
}

it('prints the resolved tokens on every guest page', function () {
    $restaurant = themedShop();

    foreach ([route('public.restaurant', $restaurant), route('public.branch', [$restaurant, 'lusail']), route('customer.sign-in', $restaurant), route('customer.account', $restaurant), route('customer.orders', $restaurant)] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toContain('--brand: #4b5d2a')
            ->and($html)->toContain('--color-ink-900: #1d2316')
            ->and($html)->toContain('--color-ivory-50: #f8f6ef')
            ->and($html)->toContain('--font-sans: ')
            ->and($html)->toContain('--color-on-brand: #');
    }
});

it('keeps the classic look byte for byte when nothing changed', function () {
    $restaurant = restaurant(slug: 'masar');

    $css = ThemeCss::for($restaurant);

    expect($css)->toContain('--brand: #0f3b3a')
        ->and($css)->not->toContain('--color-ink-')
        ->and($css)->not->toContain('--color-ivory-')
        ->and($css)->not->toContain('--font-sans')
        ->and($css)->not->toContain('@font-face')
        ->and($css)->toContain('--radius-scale: 1');
});

it('inverts ink for dark themes and sets color-scheme dark', function () {
    $restaurant = themedShop('midnight-brass');
    $html = $this->get(route('public.restaurant', $restaurant))->getContent();
    $spec = ThemeResolver::for($restaurant);

    expect($html)->toContain('<meta name="color-scheme" content="dark">')
        ->and($html)->toContain('--color-ink-900: '.$spec->colors['text'])
        ->and($html)->toContain('--color-white: #')
        ->and($html)->not->toContain('--color-white: #ffffff')
        ->and($html)->toContain('data-theme-mode="dark"');
});

it('links only the chosen fonts and nothing remote', function () {
    $restaurant = themedShop('rose-cafe');
    $html = $this->get(route('public.restaurant', $restaurant))->getContent();

    expect($html)->toContain('fonts/guest/tajawal/')
        ->and($html)->toContain('fonts/guest/dm-serif-display/')
        ->and($html)->not->toContain('fonts/guest/amiri/')
        ->and($html)->toContain('rel="preload"');

    preg_match_all('/(?:href|src)="(https?:[^"]+)"|url\((https?:[^)]+)\)/', $html, $urls);
    foreach (array_filter([...$urls[1], ...$urls[2]]) as $url) {
        expect($url)->toStartWith(config('app.url'));
    }
});

it('scales corners for soft and sharp themes', function () {
    expect(ThemeCss::for(themedShop('mono-bold')))->toContain('--radius-scale: 0.2')
        ->and(ThemeCss::for(themedShop('desert-sand', 'sahra')))->toContain('--radius-scale: 0.6');
});

it('caches the css and refreshes on save', function () {
    $restaurant = themedShop();
    $first = ThemeCss::for($restaurant);

    DB::enableQueryLog();
    expect(ThemeCss::for($restaurant))->toBe($first)
        ->and(DB::getQueryLog())->toBe([]);

    ThemeResolver::save($restaurant, 'olive-grove', ThemeResolver::for($restaurant)->with(['colors' => ['brand' => '#33441a']]));

    expect(ThemeCss::for($restaurant->fresh()))->toContain('--brand: #33441a');
});

it('shows a draft only to its author on the same restaurant', function () {
    $restaurant = themedShop();
    $other = themedShop('olive-grove', 'sahra');
    $owner = ownerOf($restaurant);
    $token = ThemePreview::put($restaurant, $owner, ThemeResolver::for($restaurant)->with(['colors' => ['brand' => '#7a1f1f']]));

    $this->actingAs($owner)->get(route('public.branch', [$restaurant, 'lusail']).'?theme_preview='.$token)
        ->assertSee('--brand: #7a1f1f', false)
        ->assertSee(__('themes.preview.ribbon'));

    $this->actingAs(employeeAt($restaurant, 'cashier'))->get(route('public.branch', [$restaurant, 'lusail']).'?theme_preview='.$token)
        ->assertSee('--brand: #4b5d2a', false)
        ->assertDontSee(__('themes.preview.ribbon'));

    $this->actingAs($owner)->get(route('public.branch', [$other, 'lusail']).'?theme_preview='.$token)
        ->assertDontSee('--brand: #7a1f1f', false);
});

it('marks preview pages noindex and no-store and turns off ordering', function () {
    $restaurant = themedShop();
    $owner = ownerOf($restaurant);
    $token = ThemePreview::put($restaurant, $owner, ThemeResolver::for($restaurant));

    $response = $this->actingAs($owner)->get(route('public.branch', [$restaurant, 'lusail']).'?theme_preview='.$token);

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->toContain('data-theme-preview');
});

it('expires a draft after 30 minutes', function () {
    $restaurant = themedShop();
    $owner = ownerOf($restaurant);
    $this->actingAs($owner);
    $token = ThemePreview::put($restaurant, $owner, ThemeResolver::for($restaurant));

    $this->travel(31)->minutes();

    expect(ThemePreview::draft($restaurant, $token))->toBeNull();
});
