<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Themes\Models\BrandFont;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeFile;
use App\Domain\Themes\ThemePreview;
use App\Domain\Themes\ThemeResolver;
use App\Filament\App\Pages\MenuDesign;
use App\Filament\App\Pages\RestaurantSettings;
use App\Livewire\Workspace\ThemeEditor;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app()->setLocale('en');
    Storage::fake('public');
});

/** @return array{Restaurant, User} */
function themeWorkspace(?string $position = null): array
{
    $restaurant = restaurant(slug: 'masar');
    Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);
    $user = $position === null ? ownerOf($restaurant) : employeeAt($restaurant, $position);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return [$restaurant, $user];
}

it('opens on the theme tab for a menu manager and is forbidden to a cashier', function () {
    [$restaurant] = themeWorkspace();

    $this->get('/app/masar/menu/design')->assertOk()->assertSeeLivewire(ThemeEditor::class);
    $this->get('/app/masar/menu/design?tab=layout')->assertOk()->assertDontSeeLivewire(ThemeEditor::class);

    $this->actingAs(employeeAt($restaurant, 'cashier'))->get('/app/masar/menu/design')->assertForbidden();
});

it('asks before replacing custom changes when picking a template', function () {
    [$restaurant] = themeWorkspace();
    ThemeResolver::save($restaurant, 'ivory-teal', ThemeResolver::for($restaurant)->with(['colors' => ['brand' => '#224466']]));

    $editor = Livewire::test(ThemeEditor::class)
        ->call('useTemplate', 'olive-grove')
        ->assertSet('pendingTemplate', 'olive-grove')
        ->assertSet('template', 'ivory-teal')
        ->assertDispatched('open-modal', id: 'theme-replace');

    $editor->call('useTemplate', 'olive-grove', true)
        ->assertSet('template', 'olive-grove')
        ->assertSet('draft.colors.brand', '#4b5d2a')
        ->assertSet('dirty', true);

    expect($restaurant->fresh()->menu_theme['template'])->toBe('ivory-teal');
});

it('saves colours, fonts and corners, audits theme.saved and changes the guest page', function () {
    [$restaurant] = themeWorkspace();

    Livewire::test(ThemeEditor::class)
        ->set('draft.colors.brand', '#7a1f1f')
        ->set('fontChoice.body.latin', 'manrope')
        ->set('draft.corners', 'sharp')
        ->call('save')
        ->assertSet('dirty', false)
        ->assertHasNoErrors();

    $spec = ThemeResolver::for($restaurant->fresh());

    expect($spec->colors['brand'])->toBe('#7a1f1f')
        ->and($spec->fonts['body'])->toBe('manrope+ibm-plex-sans-arabic')
        ->and($spec->corners)->toBe('sharp')
        ->and(Activity::query()->where('event', 'theme.saved')->exists())->toBeTrue();

    $this->get(route('public.restaurant', $restaurant))->assertSee('--brand: #7a1f1f', false);
});

it('refuses to save unreadable colours with the ratio in the message', function () {
    [$restaurant] = themeWorkspace();
    $before = $restaurant->fresh()->menu_theme;

    Livewire::test(ThemeEditor::class)
        ->set('draft.colors.text', '#eeeeee')
        ->call('save')
        ->assertSee(':1)')
        ->assertSet('dirty', true);

    expect($restaurant->fresh()->menu_theme)->toBe($before);
});

it('refreshes the preview token on each change and the token shows the draft', function () {
    [$restaurant] = themeWorkspace();

    $editor = Livewire::test(ThemeEditor::class);
    $first = $editor->get('previewToken');
    $editor->set('draft.colors.brand', '#123456');

    expect($editor->get('previewToken'))->not->toBe($first)
        ->and(ThemePreview::draft($restaurant, $editor->get('previewToken'))?->colors['brand'])->toBe('#123456')
        ->and($editor->get('previewUrl'))->toContain('/masar/lusail?theme_preview=');
});

it('resets to the template and audits theme.reset', function () {
    [$restaurant] = themeWorkspace();
    ThemeResolver::save($restaurant, 'olive-grove', ThemeResolver::for($restaurant)->with(['colors' => ['brand' => '#33441a']]));

    Livewire::test(ThemeEditor::class)->call('resetToTemplate')->assertSet('draft.colors.brand', '#4b5d2a');

    expect($restaurant->fresh()->menu_theme['overrides'])->toBe([])
        ->and(Activity::query()->where('event', 'theme.reset')->exists())->toBeTrue();
});

it('exports a theme file and imports it back after a preview', function () {
    [$restaurant] = themeWorkspace();
    $before = $restaurant->fresh()->menu_theme;

    Livewire::test(ThemeEditor::class)->call('export')->assertFileDownloaded('masar-theme.dtheme.json');

    $olive = MenuTheme::query()->where('key', 'olive-grove')->firstOrFail()->spec();
    $file = UploadedFile::fake()->createWithContent('olive.dtheme.json', ThemeFile::export($olive->with(['colors' => ['brand' => '#2f3d18']]), ['en' => 'Our olive', 'ar' => 'زيتوننا'], 'olive-grove'));

    Livewire::test(ThemeEditor::class)
        ->set('importUpload', $file)
        ->assertSet('pendingImport.base', 'olive-grove')
        ->assertSee('Our olive')
        ->call('applyImport')
        ->assertSet('template', 'olive-grove')
        ->assertSet('draft.colors.brand', '#2f3d18')
        ->assertSet('pendingImport', null)
        ->assertSet('dirty', true);

    expect($restaurant->fresh()->menu_theme)->toBe($before)
        ->and(Activity::query()->where('event', 'theme.imported')->exists())->toBeTrue();
});

it('shows a clear error for a file that is not a theme', function () {
    themeWorkspace();

    Livewire::test(ThemeEditor::class)
        ->set('importUpload', UploadedFile::fake()->createWithContent('menu.dtheme.json', '{"hello": "world"}'))
        ->assertSet('pendingImport', null)
        ->assertSee(__('themes.errors.not_theme'));
});

it('uploads and deletes a brand font from the editor', function () {
    [$restaurant] = themeWorkspace();

    $editor = Livewire::test(ThemeEditor::class)
        ->set('fontUpload', UploadedFile::fake()->createWithContent('brand.woff2', (string) file_get_contents(base_path('tests/Fixtures/fonts/sample.woff2'))))
        ->set('fontName', 'Masar Sans')
        ->set('fontScripts', 'both')
        ->call('uploadFont')
        ->assertHasNoErrors();

    $font = BrandFont::withoutTenancy()->where('restaurant_id', $restaurant->id)->sole();
    $editor->call('deleteFont', $font->id);

    expect(BrandFont::withoutTenancy()->count())->toBe(0);
});

it('uploads a cover', function () {
    [$restaurant] = themeWorkspace();

    Livewire::test(ThemeEditor::class)->set('coverUpload', UploadedFile::fake()->image('cover.jpg', 2000, 1000));

    expect($restaurant->fresh()->cover_path)->toEndWith('-1600.webp');
});

it('moved the brand colour from restaurant settings', function () {
    themeWorkspace();

    Livewire::test(RestaurantSettings::class)
        ->assertDontSeeHtml('data.accent_color')
        ->assertSeeHtml(e(MenuDesign::getUrl(['tab' => 'theme'])));
});
