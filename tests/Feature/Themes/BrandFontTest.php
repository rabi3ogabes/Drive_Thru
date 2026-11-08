<?php

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Themes\Actions\DeleteBrandFont;
use App\Domain\Themes\Actions\ProcessThemeCover;
use App\Domain\Themes\Actions\UploadBrandFont;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\BrandFont;
use App\Domain\Themes\ThemeCss;
use App\Domain\Themes\ThemeResolver;
use App\Domain\Themes\ThemeSpec;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('public');
});

function woff2(string $name = 'brand.woff2'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents(base_path('tests/Fixtures/fonts/sample.woff2')));
}

function uploadFont(Restaurant $restaurant, string $scripts = 'latin', ?UploadedFile $file = null): BrandFont
{
    return app(UploadBrandFont::class)->handle($restaurant, ownerOf($restaurant), $file ?? woff2(), 'Masar Sans', $scripts);
}

function themeReason(Closure $action): string
{
    try {
        $action();
    } catch (ThemeRejected $e) {
        return $e->reason;
    }

    return 'accepted';
}

it('stores a real WOFF2 under the restaurant with a random name and audits it', function () {
    $restaurant = restaurant();
    $font = uploadFont($restaurant);

    expect($font->restaurant_id)->toBe($restaurant->id)
        ->and($font->path)->toStartWith("brand-fonts/{$restaurant->id}/")
        ->and($font->path)->not->toContain('brand.woff2')
        ->and($font->size_bytes)->toBe(24744)
        ->and(Storage::disk('public')->exists($font->path))->toBeTrue()
        ->and(Activity::query()->where('event', 'brand_font.uploaded')->exists())->toBeTrue();
});

it('refuses a renamed TTF or PNG', function () {
    $restaurant = restaurant();

    expect(themeReason(fn () => uploadFont($restaurant, file: UploadedFile::fake()->image('logo.woff2'))))->toBe('not_woff2')
        ->and(themeReason(fn () => uploadFont($restaurant, file: UploadedFile::fake()->createWithContent('font.woff2', "\x00\x01\x00\x00fake ttf"))))->toBe('not_woff2');
});

it('refuses files over 1 MB', function () {
    $big = UploadedFile::fake()->createWithContent('big.woff2', 'wOF2'.str_repeat("\0", 1048577));

    expect(themeReason(fn () => uploadFont(restaurant(), file: $big)))->toBe('font_too_large');
});

it('allows at most four fonts', function () {
    $restaurant = restaurant();
    foreach (range(1, 4) as $i) {
        uploadFont($restaurant);
    }

    expect(themeReason(fn () => uploadFont($restaurant)))->toBe('font_limit');
});

it('refuses to delete a font the theme uses, naming the role', function () {
    $restaurant = restaurant();
    $font = uploadFont($restaurant);
    ThemeResolver::save($restaurant, 'ivory-teal', ThemeResolver::for($restaurant)->with(['fonts' => ['heading' => "brand:{$font->id}"]], $restaurant));

    try {
        app(DeleteBrandFont::class)->handle($font, ownerOf($restaurant));
        $this->fail('deleted');
    } catch (ThemeRejected $e) {
        expect($e->reason)->toBe('font_in_use')->and($e->context['role'])->toBe(__('themes.fonts.heading'));
    }

    $unused = uploadFont($restaurant);
    app(DeleteBrandFont::class)->handle($unused, ownerOf($restaurant));

    expect(BrandFont::withoutTenancy()->find($unused->id))->toBeNull()
        ->and(Storage::disk('public')->exists($unused->path))->toBeFalse()
        ->and(Activity::query()->where('event', 'brand_font.deleted')->exists())->toBeTrue();
});

it('keeps fonts to their restaurant', function () {
    $masar = restaurant(slug: 'masar');
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء'], slug: 'sahra');
    $font = uploadFont($masar);

    app(TenantContext::class)->set($sahra);
    expect(BrandFont::query()->count())->toBe(0);

    $this->actingAs(ownerOf($sahra));
    app(TenantContext::class)->set($sahra);
    expect(Gate::allows('delete', $font))->toBeFalse();

    expect(themeReason(fn () => ThemeResolver::for($sahra)->with(['fonts' => ['heading' => "brand:{$font->id}"]], $sahra)))->toBe('unknown_font')
        ->and(themeReason(fn () => ThemeSpec::fromArray(['fonts' => ['heading' => "brand:{$font->id}"]], ThemeResolver::for($sahra))))->toBe('unknown_font');
});

it('serves an uploaded font from our own storage, with the template font for the other script', function () {
    $restaurant = restaurant();
    $font = uploadFont($restaurant, 'latin');
    ThemeResolver::save($restaurant, 'ivory-teal', ThemeResolver::for($restaurant)->with(['fonts' => ['body' => "brand:{$font->id}"]], $restaurant));

    $css = ThemeCss::for($restaurant->fresh());

    expect($css)->toContain("font-family: 'brand-{$font->id}'")
        ->and($css)->toContain(Storage::disk('public')->url($font->path))
        ->and($css)->toContain("--font-sans: 'brand-{$font->id}', 'dt-geist', 'dt-ibm-plex-sans-arabic'")
        ->and($css)->not->toContain('Masar Sans');
});

it('re-encodes a cover to WebP at 800 and 1600 and strips metadata', function () {
    $restaurant = restaurant();
    $image = imagecreatetruecolor(2400, 1200);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 20, 80, 70));
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    // An Exif APP1 segment right after the start-of-image marker, with a location tag.
    $exif = "Exif\0\0GPS-SECRET-LOCATION";
    $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    $path = app(ProcessThemeCover::class)->handle($restaurant, ownerOf($restaurant), UploadedFile::fake()->createWithContent('cover.jpg', $jpeg));
    $bytes = (string) Storage::disk('public')->get($path);

    expect($path)->toEndWith('-1600.webp')
        ->and(substr($bytes, 0, 4))->toBe('RIFF')
        ->and(substr($bytes, 8, 4))->toBe('WEBP')
        ->and($bytes)->not->toContain('GPS-SECRET')
        ->and(getimagesizefromstring($bytes)[0])->toBe(1600)
        ->and(Storage::disk('public')->exists(str_replace('-1600.webp', '-800.webp', $path)))->toBeTrue();
});

it('refuses covers that are not images or are over 5 MB', function () {
    $restaurant = restaurant();
    $cover = fn (UploadedFile $file) => fn () => app(ProcessThemeCover::class)->handle($restaurant, ownerOf($restaurant), $file);

    expect(themeReason($cover(UploadedFile::fake()->createWithContent('cover.jpg', '<?php phpinfo();'))))->toBe('bad_image')
        ->and(themeReason($cover(UploadedFile::fake()->create('cover.jpg', 6000, 'image/jpeg'))))->toBe('image_too_large');
});

it('slows down after 20 uploads an hour', function () {
    $restaurant = restaurant();
    $owner = ownerOf($restaurant);
    RateLimiter::clear('theme-upload:'.$owner->id);

    foreach (range(1, 20) as $i) {
        RateLimiter::hit('theme-upload:'.$owner->id, 3600);
    }

    expect(themeReason(fn () => app(UploadBrandFont::class)->handle($restaurant, $owner, woff2(), 'Masar Sans', 'latin')))->toBe('slow_down');
});
