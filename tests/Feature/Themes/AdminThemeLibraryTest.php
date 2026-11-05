<?php

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeFile;
use App\Domain\Themes\ThemeResolver;
use App\Filament\Admin\Resources\MenuThemes\Pages\CreateMenuTheme;
use App\Filament\Admin\Resources\MenuThemes\Pages\EditMenuTheme;
use App\Filament\Admin\Resources\MenuThemes\Pages\ListMenuThemes;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app()->setLocale('en');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function themeAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    test()->actingAs($admin);

    return $admin;
}

function olive(): MenuTheme
{
    return MenuTheme::query()->where('key', 'olive-grove')->firstOrFail();
}

it('lists templates with how many restaurants use each', function () {
    themeAdmin();
    foreach (['masar', 'sahra'] as $slug) {
        $restaurant = restaurant(slug: $slug);
        ThemeResolver::save($restaurant, 'olive-grove', olive()->spec());
    }

    DB::enableQueryLog();
    Livewire::test(ListMenuThemes::class)
        ->assertCanSeeTableRecords(MenuTheme::query()->get())
        ->assertSee('Olive Grove')
        ->assertTableColumnStateSet('used_by', 2, olive());

    expect(collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'restaurants'))->count())->toBeLessThanOrEqual(2);
});

it('creates a template and audits menu_theme.created', function () {
    themeAdmin();

    Livewire::test(CreateMenuTheme::class)
        ->fillForm([
            'key' => 'night-market',
            'name' => ['en' => 'Night Market', 'ar' => 'سوق الليل'],
            'description' => ['en' => 'Neon on black', 'ar' => 'نيون على أسود'],
            'spec' => ['mode' => 'dark', 'colors' => ['brand' => '#ff5c8a', 'accent' => '#7cf7d4', 'background' => '#0b0b10', 'text' => '#f5f5f7'], 'corners' => 'soft', 'layout' => ['card_style' => 'grid', 'image_style' => 'square']],
            'heading_latin' => 'outfit', 'heading_arabic' => 'cairo', 'body_latin' => 'manrope', 'body_arabic' => 'readex-pro',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $theme = MenuTheme::query()->where('key', 'night-market')->sole();

    expect($theme->spec()->fonts)->toBe(['heading' => 'outfit+cairo', 'body' => 'manrope+readex-pro'])
        ->and($theme->is_built_in)->toBeFalse()
        ->and(Activity::query()->where('event', 'menu_theme.created')->exists())->toBeTrue();
});

it('refuses an unreadable template', function () {
    themeAdmin();

    Livewire::test(CreateMenuTheme::class)
        ->fillForm([
            'key' => 'foggy',
            'name' => ['en' => 'Foggy', 'ar' => 'ضبابي'],
            'spec' => ['mode' => 'light', 'colors' => ['brand' => '#0f3b3a', 'accent' => '#b08d57', 'background' => '#ffffff', 'text' => '#dddddd'], 'corners' => 'rounded', 'layout' => ['card_style' => 'grid', 'image_style' => 'square']],
            'heading_latin' => 'fraunces', 'heading_arabic' => 'alexandria', 'body_latin' => 'geist', 'body_arabic' => 'ibm-plex-sans-arabic',
        ])
        ->call('create')
        ->assertHasFormErrors(['spec.colors.text']);

    expect(MenuTheme::query()->where('key', 'foggy')->exists())->toBeFalse();
});

it('duplicates a template and uploads one from a theme file as hidden', function () {
    themeAdmin();

    Livewire::test(ListMenuThemes::class)->callTableAction('duplicate', olive());

    $copy = MenuTheme::query()->where('key', 'like', 'olive-grove-copy%')->sole();
    expect($copy->is_built_in)->toBeFalse()->and($copy->is_default)->toBeFalse()->and($copy->spec()->colors)->toBe(olive()->spec()->colors);

    $file = UploadedFile::fake()->createWithContent('rose.dtheme.json', ThemeFile::export(olive()->spec()->with(['colors' => ['brand' => '#7a1f3d']]), ['en' => 'Rosewood', 'ar' => 'خشب الورد'], 'olive-grove'));

    Livewire::test(ListMenuThemes::class)
        ->mountAction('uploadFile')
        ->fillForm(['file' => $file])
        ->callMountedAction()
        ->assertHasNoErrors();

    $uploaded = MenuTheme::query()->where('key', 'rosewood')->sole();
    expect($uploaded->is_active)->toBeFalse()
        ->and($uploaded->spec()->colors['brand'])->toBe('#7a1f3d')
        ->and($uploaded->getTranslation('name', 'ar'))->toBe('خشب الورد');
});

it('keeps exactly one default and only new restaurants take it', function () {
    themeAdmin();
    $existing = restaurant(slug: 'masar');

    Livewire::test(ListMenuThemes::class)->callTableAction('setDefault', olive());

    expect(MenuTheme::query()->where('is_default', true)->pluck('key')->all())->toBe(['olive-grove'])
        ->and(Activity::query()->where('event', 'menu_theme.default_changed')->exists())->toBeTrue()
        ->and(ThemeResolver::template($existing->fresh())->key)->toBe('ivory-teal');

    $new = app(CreateRestaurant::class)->handle(['en' => 'Fresh', 'ar' => 'طازج'], owner: User::factory()->create());

    expect(ThemeResolver::template($new->fresh())->key)->toBe('olive-grove');
});

it('hides but never deletes a template in use or a built-in one', function () {
    $admin = themeAdmin();
    $restaurant = restaurant(slug: 'masar');
    ThemeResolver::save($restaurant, 'olive-grove', olive()->spec());

    Livewire::test(ListMenuThemes::class)
        ->assertTableActionHidden('delete', olive())
        ->callTableAction('hide', olive());

    expect(olive()->is_active)->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', olive()))->toBeFalse()
        ->and(Activity::query()->where('event', 'menu_theme.hidden')->exists())->toBeTrue()
        ->and(ThemeResolver::template($restaurant->fresh())->key)->toBe('olive-grove');

    $copy = olive()->replicate()->fill(['key' => 'spare', 'is_built_in' => false, 'is_default' => false]);
    $copy->save();
    expect(Gate::forUser($admin)->allows('delete', $copy))->toBeTrue();
});

it('clears the theme cache of restaurants using an edited template', function () {
    themeAdmin();
    $restaurant = restaurant(slug: 'masar');
    Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);
    ThemeResolver::save($restaurant, 'olive-grove', olive()->spec());
    $this->get(route('public.restaurant', $restaurant))->assertSee('--brand: #4b5d2a', false);

    Livewire::test(EditMenuTheme::class, ['record' => olive()->getRouteKey()])
        ->fillForm(['spec' => ['colors' => ['brand' => '#2e3a14']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::query()->where('event', 'menu_theme.updated')->exists())->toBeTrue();
    $this->get(route('public.restaurant', $restaurant))->assertSee('--brand: #2e3a14', false);
});

it('is limited to platform staff with Manage themes', function () {
    $restaurant = restaurant(slug: 'masar');
    $this->actingAs(ownerOf($restaurant))->get('/admin/menu-themes')->assertForbidden();

    $staff = User::factory()->create();
    $staff->forceFill(['platform_role_id' => PlatformRole::factory()->granting([PlatformPermission::ViewClients])->create()->id])->save();
    $this->actingAs($staff)->get('/admin/menu-themes')->assertForbidden();

    $themer = User::factory()->create();
    $themer->forceFill(['platform_role_id' => PlatformRole::factory()->granting([PlatformPermission::ManageThemes])->create()->id])->save();
    $this->actingAs($themer)->get('/admin/menu-themes')->assertOk()->assertSee('Olive Grove');
});
