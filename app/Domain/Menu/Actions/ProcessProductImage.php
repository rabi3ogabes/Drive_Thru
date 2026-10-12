<?php

declare(strict_types=1);

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\MenuCache;
use App\Domain\Menu\Models\Product;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Turns an uploaded photo into light, responsive WebP and AVIF versions
 * (400 and 800 px wide). Guests get the smallest file their screen needs;
 * the original stays as the fallback.
 */
final class ProcessProductImage
{
    public const WIDTHS = [400, 800];

    public function handle(Product $product): void
    {
        $disk = Storage::disk('public');
        $path = $product->image_path;

        if ($path === null || ! $disk->exists($path)) {
            $product->forceFill(['image_variants' => null])->saveQuietly();

            return;
        }

        $source = @imagecreatefromstring((string) $disk->get($path));

        if (! $source instanceof GdImage) {
            return;
        }

        $variants = [];
        $base = 'menu/'.$product->restaurant_id.'/'.$product->id.'/'.pathinfo($path, PATHINFO_FILENAME);

        foreach (self::WIDTHS as $width) {
            $resized = imagesx($source) > $width ? imagescale($source, $width) : $source;

            if (! $resized instanceof GdImage) {
                continue;
            }

            foreach (['webp' => fn ($img) => imagewebp($img, null, 80), 'avif' => fn ($img) => function_exists('imageavif') && imageavif($img, null, 55, 6)] as $format => $encode) {
                ob_start();
                $ok = $encode($resized);
                $bytes = (string) ob_get_clean();

                if ($ok && $bytes !== '') {
                    $target = "{$base}-{$width}.{$format}";
                    $disk->put($target, $bytes, 'public');
                    $variants[$format][(string) $width] = $target;
                }
            }
        }

        $product->forceFill(['image_variants' => $variants === [] ? null : $variants])->saveQuietly();

        // Variant URLs live in the cached catalog.
        MenuCache::bump($product->restaurant_id);
    }
}
