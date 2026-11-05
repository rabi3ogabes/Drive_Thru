<?php

declare(strict_types=1);

namespace App\Domain\Themes\Actions;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\BrandFont;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores an uploaded brand font. Only real WOFF2 files are kept (checked by
 * their signature, not the file name), up to 1 MB and 4 per restaurant,
 * under a random name in the restaurant's own folder.
 */
final class UploadBrandFont
{
    public function handle(Restaurant $restaurant, User $user, UploadedFile $file, string $name, string $scripts): BrandFont
    {
        UploadThrottle::hit($user);

        if (! in_array($scripts, BrandFont::SCRIPTS, true)) {
            throw new ThemeRejected('bad_value', ['field' => 'scripts']);
        }

        $name = mb_substr(trim(strip_tags($name)), 0, 60);
        if ($name === '') {
            throw new ThemeRejected('font_name');
        }

        if ((int) $file->getSize() > BrandFont::MAX_BYTES) {
            throw new ThemeRejected('font_too_large');
        }

        $handle = fopen((string) $file->getRealPath(), 'rb');
        $signature = $handle !== false ? (string) fread($handle, 4) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        if ($signature !== 'wOF2') {
            throw new ThemeRejected('not_woff2');
        }

        if (BrandFont::withoutTenancy()->where('restaurant_id', $restaurant->id)->count() >= BrandFont::LIMIT) {
            throw new ThemeRejected('font_limit', ['count' => BrandFont::LIMIT]);
        }

        $path = $file->storeAs("brand-fonts/{$restaurant->id}", Str::uuid()->toString().'.woff2', 'public');

        $font = BrandFont::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'name' => $name,
            'scripts' => $scripts,
            'path' => (string) $path,
            'size_bytes' => (int) $file->getSize(),
        ]);

        activity()->performedOn($font)->causedBy($user)->event('brand_font.uploaded')
            ->withProperties(['restaurant_id' => $restaurant->id, 'name' => $name])->log('Brand font uploaded');

        return $font;
    }
}
