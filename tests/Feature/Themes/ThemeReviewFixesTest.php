<?php

use App\Domain\Tenancy\TenantContext;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeResolver;
use App\Filament\Admin\Resources\MenuThemes\Pages\EditMenuTheme;
use App\Filament\Admin\Resources\MenuThemes\Pages\ListMenuThemes;
use App\Filament\Admin\Resources\Restaurants\Pages\EditRestaurant;
use App\Livewire\Workspace\ThemeEditor;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(fn () => app()->setLocale('en'));

function spareTemplate(string $key = 'spare'): MenuTheme
{
    $theme = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->replicate();
    $theme->fill(['key' => $key, 'is_built_in' => false, 'is_default' => false])->save();

    return $theme;
}

it('keeps a template key fixed after creation, so restaurants using it keep their theme', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $theme = spareTemplate();
    $restaurant = restaurant(slug: 'masar');
    ThemeResolver::save($restaurant, 'spare', $theme->spec());

    Livewire::test(EditMenuTheme::class, ['record' => $theme->getRouteKey()])
        ->assertFormFieldIsDisabled('key')
        ->fillForm(['key' => 'renamed'])
        ->call('save');

    expect($theme->fresh()->key)->toBe('spare')
        ->and(ThemeResolver::template($restaurant->fresh())->key)->toBe('spare');
});

it('never puts white text on a brand-coloured surface in guest views', function () {
    $files = [...glob(resource_path('views/livewire/customer/*.blade.php')), ...glob(resource_path('views/livewire/customer/partials/*.blade.php')), ...glob(resource_path('views/components/customer/*.blade.php')), resource_path('views/components/stamp-card.blade.php')];
    $offenders = [];

    foreach ($files as $file) {
        $lines = file($file);
        foreach ($lines as $i => $line) {
            if (! str_contains($line, 'bg-[var(--brand)]') && ! str_contains($line, 'text-on-brand')) {
                continue;
            }
            // The element itself and its first children (the next 3 lines) follow the brand's text colour.
            foreach (array_slice($lines, max(0, $i - 2), 6, true) as $n => $near) {
                // Dark ink pills keep white text: they invert together with it in dark themes.
                if (preg_match('/\btext-white\b/', $near) === 1 && preg_match('/bg-ink-[6-9]\d\d\b/', $near) !== 1) {
                    $offenders[] = basename($file).':'.($n + 1);
                }
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([]);
});

it('no longer edits the brand colour from the admin restaurant form', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $restaurant = restaurant(slug: 'masar');

    Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
        ->assertFormFieldDoesNotExist('accent_color');
});

it('tells the owner that logo and cover changes are live straight away and confirms removing the cover', function () {
    $restaurant = restaurant(slug: 'masar');
    $restaurant->forceFill(['cover_path' => 'covers/1/x-1600.webp'])->save();
    $this->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    Livewire::test(ThemeEditor::class)
        ->assertSee(__('themes.editor.brand_instant'))
        ->assertSeeHtml('wire:confirm="'.e(__('themes.editor.remove_cover_confirm')).'"');
});

it('counts template usage once per page, however many templates there are', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    foreach (range(1, 8) as $i) {
        spareTemplate("spare-{$i}");
    }
    restaurant(slug: 'masar');

    DB::enableQueryLog();
    Livewire::test(ListMenuThemes::class)->assertOk();

    $restaurantQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => preg_match('/from "?restaurants"?/', $q['query']) === 1)->count();
    expect($restaurantQueries)->toBeLessThanOrEqual(1);
});
