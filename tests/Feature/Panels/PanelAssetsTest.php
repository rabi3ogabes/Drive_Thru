<?php

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Models\User;

/*
 * Regression: without published Filament scripts every form renders but no
 * button or field works (Alpine components are undefined). `composer install`
 * publishes them through `filament:upgrade`; this guards against losing that.
 */
it('has the Filament scripts published', function (string $script) {
    expect(public_path("js/filament/{$script}"))->toBeFile();
})->with([
    'panels' => 'filament/app.js',
    'schemas' => 'schemas/schemas.js',
    'actions' => 'actions/actions.js',
    'notifications' => 'notifications/notifications.js',
]);

it('renders avatars locally without calling a third-party service', function () {
    $user = User::factory()->make(['name' => 'Khalid Al-Mansoori']);

    $url = (new InitialsAvatarProvider)->get($user);

    expect($url)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($url, strlen('data:image/svg+xml;base64,'))))->toContain('>KA<');
});

it('uses Arabic initials for Arabic names and escapes markup', function () {
    $arabic = (new InitialsAvatarProvider)->get(User::factory()->make(['name' => 'خالد المنصوري']));
    $hostile = (new InitialsAvatarProvider)->get(User::factory()->make(['name' => '<script> x']));

    expect(base64_decode(substr($arabic, 26)))->toContain('>خا<')
        ->and(base64_decode(substr($hostile, 26)))->not->toContain('<script');
});

it('keeps the sign-in page free of external requests', function () {
    $html = $this->get('/admin/login')->assertOk()->getContent();

    expect($html)->not->toContain('ui-avatars.com')
        ->not->toContain('fonts.bunny.net')
        ->not->toContain('fonts.googleapis.com');
});
