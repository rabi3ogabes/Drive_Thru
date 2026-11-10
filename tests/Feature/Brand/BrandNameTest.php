<?php

use App\Domain\Platform\PlatformSettings;
use App\Support\Brand;
use Illuminate\Support\Facades\File;

it('names the platform in each language', function () {
    app()->setLocale('ar');
    expect(Brand::name())->toBe('ذوق');

    app()->setLocale('en');
    expect(Brand::name())->toBe('Dhawq')
        ->and(Brand::name('ar'))->toBe('ذوق')
        ->and(Brand::latin())->toBe('Dhawq');
});

it('keeps the bilingual brand in a fixed order', function () {
    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        expect(Brand::full())->toBe('ذوق · Dhawq');
    }
});

it('no longer uses the old name anywhere', function () {
    $skip = ['public/build', 'public/vendor', 'public/js/filament', 'public/css/filament', 'public/fonts', 'public/storage'];
    $pattern = '/Drive-Thru Plus|Drive-Thru\+|Drive-Thru<span[^>]*>\+|Drive\x{2060}-\x{2060}Thru[ \x{00A0}]Plus|درايف ثرو بلس/u';
    $offenders = [];

    foreach (['app', 'lang', 'resources', 'config', 'public'] as $root) {
        foreach (File::allFiles(base_path($root)) as $file) {
            $relative = str_replace('\\', '/', $root.'/'.$file->getRelativePathname());

            if (! in_array($file->getExtension(), ['php', 'js', 'json', 'css', 'svg'], true) || collect($skip)->contains(fn (string $s): bool => str_starts_with($relative, $s))) {
                continue;
            }

            foreach (file($file->getPathname()) as $n => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = $relative.':'.($n + 1);
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('greets invited people and leads with the new brand', function () {
    app()->setLocale('en');
    expect(__('access.invitation.mail.body', ['place' => 'Masar']))->toContain('Dhawq')
        ->and(__('leads.whatsapp_greeting', ['name' => 'Sara']))->toContain('Dhawq');

    app()->setLocale('ar');
    expect(__('access.invitation.mail.body', ['place' => 'Masar']))->toContain('ذوق')
        ->and(__('leads.whatsapp_greeting', ['name' => 'Sara']))->toContain('ذوق');
});

it('shows the brand on the admin and workspace sign-in pages and the site', function () {
    app()->setLocale('en');

    $this->get('/admin/login')->assertOk()->assertSee('Dhawq');
    $this->get('/app/login')->assertOk()->assertSee('Dhawq');
});

it('renames the stored legal name only when it is the old default', function () {
    $migration = require database_path('migrations/2026_11_10_100000_rename_platform_legal_name.php');

    app(PlatformSettings::class)->set(['billing.legal_name' => 'Drive-Thru Plus']);
    $migration->up();
    expect(app(PlatformSettings::class)->string('billing.legal_name'))->toBe('Dhawq');

    app(PlatformSettings::class)->set(['billing.legal_name' => 'Masar Holding W.L.L.']);
    $migration->up();
    expect(app(PlatformSettings::class)->string('billing.legal_name'))->toBe('Masar Holding W.L.L.');
});
