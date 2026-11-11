<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Themes\Actions\ProcessThemeCover;
use App\Domain\Themes\Contrast;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeCss;
use App\Domain\Themes\ThemePreview;
use App\Domain\Themes\ThemeResolver;
use App\Domain\Themes\ThemeTokens;
use App\Filament\Admin\Resources\MenuThemes\Pages\EditMenuTheme;
use App\Livewire\Workspace\ThemeEditor;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    app()->setLocale('en');
    Storage::fake('public');
});

function minorWorkspace(): Restaurant
{
    $restaurant = restaurant(slug: 'masar');
    Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);
    test()->actingAs(ownerOf($restaurant));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return $restaurant;
}

it('does not let an owner switch to a hidden template by tampering with the request', function () {
    minorWorkspace();
    MenuTheme::query()->where('key', 'rose-cafe')->update(['is_active' => false]);

    expect(fn () => Livewire::test(ThemeEditor::class)->set('template', 'rose-cafe'))->toThrow(CannotUpdateLockedPropertyException::class);

    Livewire::test(ThemeEditor::class)->call('useTemplate', 'rose-cafe', true)->assertNotFound();
});

it('keeps the default template offered even from its edit form', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $default = MenuTheme::query()->where('is_default', true)->sole();

    Livewire::test(EditMenuTheme::class, ['record' => $default->getRouteKey()])
        ->assertFormFieldIsDisabled('is_active')
        ->fillForm(['is_active' => false])
        ->call('save');

    expect($default->fresh()->is_active)->toBeTrue();
});

it('refuses cover images with too many pixels before decoding them', function () {
    $restaurant = restaurant();
    // A tiny file whose PNG header claims 9000 × 9000 pixels.
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 9000, 9000)."\x08\x02\x00\x00\x00".pack('N', 0);

    try {
        app(ProcessThemeCover::class)->handle($restaurant, ownerOf($restaurant), UploadedFile::fake()->createWithContent('huge.png', $png));
        $this->fail('accepted');
    } catch (ThemeRejected $e) {
        expect($e->reason)->toBe('image_too_big');
    }
});

it('serves the 800 px cover to small screens and deletes a replaced cover', function () {
    $restaurant = minorWorkspace();

    $editor = Livewire::test(ThemeEditor::class)->set('coverUpload', UploadedFile::fake()->image('a.jpg', 2000, 1000));
    $first = $restaurant->fresh()->cover_path;

    $this->get(route('public.branch', [$restaurant, 'lusail']))
        ->assertSee(str_replace('-1600.webp', '-800.webp', Storage::disk('public')->url($first)).' 800w', false)
        ->assertSee(Storage::disk('public')->url($first).' 1600w', false);

    $editor->set('coverUpload', UploadedFile::fake()->image('b.jpg', 2000, 1000));

    expect(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists(str_replace('-1600.webp', '-800.webp', $first)))->toBeFalse()
        ->and(Storage::disk('public')->exists($restaurant->fresh()->cover_path))->toBeTrue();

    $second = $restaurant->fresh()->cover_path;
    $editor->call('removeCover');

    expect(Storage::disk('public')->exists($second))->toBeFalse()
        ->and($restaurant->fresh()->cover_path)->toBeNull();
});

it('gives accent surfaces readable text and warns when no colour can', function () {
    $classic = ThemeResolver::for(restaurant());

    expect(ThemeTokens::for($classic)['color-on-accent'])->toBe('#111827')
        ->and(Contrast::ratio(ThemeTokens::onAccent($classic), $classic->colors['accent']))->toBeGreaterThanOrEqual(4.5)
        ->and(ThemeCss::for(restaurant(slug: 'sahra')))->toContain('--color-on-accent: #111827');

    // Neither white (4.3:1) nor the dark grey text (2.6:1) reads on this accent.
    $grey = $classic->with(['colors' => ['accent' => '#7a7a7a', 'text' => '#3a3a3a']]);
    expect(array_column(Contrast::check($grey)['warnings'], 'key'))->toContain('accent_text');

    $views = file_get_contents(resource_path('views/livewire/customer/rewards.blade.php'));
    expect(preg_match('/bg-brass-500[^\'"]*\btext-white\b/', $views))->toBe(0);
});

it('drops the previous preview draft when a new one is stored', function () {
    $restaurant = minorWorkspace();
    $editor = Livewire::test(ThemeEditor::class);
    $first = $editor->get('previewToken');

    $editor->set('draft.colors.brand', '#123456');

    expect(ThemePreview::draft($restaurant, $first))->toBeNull()
        ->and(ThemePreview::draft($restaurant, $editor->get('previewToken')))->not->toBeNull();
});
