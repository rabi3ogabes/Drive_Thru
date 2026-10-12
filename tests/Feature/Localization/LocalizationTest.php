<?php

use App\Http\Middleware\NormalizeDigits;
use App\Models\User;
use App\Support\Digits;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

it('defaults to Arabic, right to left', function () {
    $this->withHeader('Accept-Language', '')->get('/')
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('app.home.headline', [], 'ar'));
});

it('follows the browser language when it is supported', function () {
    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get('/')
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee('From scan to handover in under a minute.');
});

it('falls back to Arabic for unsupported browser languages', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/')->assertSee('lang="ar"', false);
});

it('switches language, remembers it and returns to the same page', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    $this->actingAs($user)
        ->from('/admin/login?lang=ar')
        ->get('/locale/en')
        ->assertRedirect('/admin/login')
        ->assertCookie('locale', 'en')
        ->assertSessionHas('locale', 'en');

    expect($user->fresh()->locale)->toBe('en');

    $this->get('/')->assertSee('lang="en"', false);
});

it('rejects unsupported languages', function () {
    $this->get('/locale/fr')->assertNotFound();
});

it('never redirects off-site after switching language', function () {
    $this->from('https://evil.example/phish')->get('/locale/en')->assertRedirect(url('/'));
});

it('converts Arabic-Indic digits typed into forms', function () {
    Route::post('/_digits', fn () => request()->only('phone', 'password'))->middleware(['web', NormalizeDigits::class]);

    $this->withoutMiddleware(PreventRequestForgery::class)
        ->post('/_digits', ['phone' => '+٩٧٤ ٥٥١٢ ٣٤٥٦', 'password' => 'كلمة٣'])
        ->assertJson(['phone' => '+974 5512 3456', 'password' => 'كلمة٣']);
});

it('normalizes Eastern Arabic and Persian digits', function () {
    expect(Digits::toLatin('٠١٢٣٤٥٦٧٨٩ ۰۱۲۳۴۵۶۷۸۹ ١٢٫٥'))->toBe('0123456789 0123456789 12.5')
        ->and(Digits::number(12450.5, 2))->toBe('12,450.50');
});

it('has every translation key in both Arabic and English', function () {
    $keys = function (string $locale): array {
        return collect(File::files(lang_path($locale)))
            ->flatMap(fn ($file) => collect(Arr::dot(require $file->getPathname()))
                ->keys()
                ->map(fn ($key) => $file->getFilenameWithoutExtension().'.'.$key))
            ->sort()
            ->values()
            ->all();
    };

    $english = $keys('en');
    $arabic = $keys('ar');

    expect(array_values(array_diff($english, $arabic)))->toBe([], 'Missing in Arabic')
        ->and(array_values(array_diff($arabic, $english)))->toBe([], 'Missing in English');
});
