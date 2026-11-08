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

    public function handle(Restaurant $restaurant, User $user, UploadedFile $file, string $folder = 'covers'): string
    {
        UploadThrottle::hit($user);

        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw new ThemeRejected('image_too_large');
        }

        $bytes = (string) file_get_contents((string) $file->getRealPath());
        $info = @getimagesizefromstring($bytes);
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
}
