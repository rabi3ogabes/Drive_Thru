<?php

declare(strict_types=1);

namespace App\Domain\Themes\Actions;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A theme cover image, re-encoded to WebP at 1600 and 800 px wide. The
 * re-encode drops everything but the pixels (Exif, location, scripts).
 * Returns the 1600 px path; the 800 px file sits next to it.
 */
final class ProcessThemeCover
{
    public const MAX_BYTES = 5242880;

    public const WIDTHS = [1600, 800];

    /** Decoding needs about 5 bytes a pixel; 24 megapixels stays well inside PHP's memory. */
    public const MAX_PIXELS = 24_000_000;

    public function handle(Restaurant $restaurant, User $user, UploadedFile $file, string $folder = 'covers'): string
    {
        UploadThrottle::hit($user);

        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw new ThemeRejected('image_too_large');
        }

        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $info = @getimagesizefromstring($bytes);

        if ($info !== false && $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new ThemeRejected('image_too_big');
        }

        $source = $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) ? @imagecreatefromstring($bytes) : false;

        if (! $source instanceof GdImage) {
            throw new ThemeRejected('bad_image');
        }

        $base = "{$folder}/{$restaurant->id}/".Str::uuid()->toString();
        $disk = Storage::disk('public');

        foreach (self::WIDTHS as $width) {
            $resized = imagesx($source) > $width ? imagescale($source, $width) : $source;

            if (! $resized instanceof GdImage) {
                throw new ThemeRejected('bad_image');
            }

            ob_start();
            imagewebp($resized, null, 82);
            $disk->put("{$base}-{$width}.webp", (string) ob_get_clean(), 'public');
        }

        return "{$base}-1600.webp";
    }

    /** The 800 px file next to a cover made here, or null for other images. */
    public static function small(?string $path): ?string
    {
        return $path !== null && str_ends_with($path, '-1600.webp') ? substr($path, 0, -strlen('-1600.webp')).'-800.webp' : null;
    }

    /** Deletes a cover this action made for the restaurant (both sizes). Other files are left alone. */
    public static function delete(Restaurant $restaurant, ?string $path, string $folder = 'covers'): void
    {
        $small = self::small($path);

        if ($small === null || ! str_starts_with((string) $path, "{$folder}/{$restaurant->id}/")) {
            return;
        }

        Storage::disk('public')->delete([(string) $path, $small]);
    }
}
