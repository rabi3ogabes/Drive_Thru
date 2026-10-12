<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages\Concerns;

use App\Domain\Menu\Actions\ProcessProductImage;
use App\Domain\Menu\MenuCache;
use App\Domain\Menu\Models\Product;

/**
 * After a product is saved from the editor: build its WebP/AVIF versions
 * when the photo changed (after the response, so saving stays instant), and
 * refresh cached menus for option/suggestion changes, which are pivot writes.
 */
trait SyncsProductMedia
{
    protected ?string $originalImagePath = null;

    protected function afterProductSaved(Product $product): void
    {
        if ($product->image_path !== $this->originalImagePath) {
            $id = $product->id;

            dispatch(function () use ($id): void {
                $fresh = Product::withoutTenancy()->find($id);

                if ($fresh !== null) {
                    app(ProcessProductImage::class)->handle($fresh);
                }
            })->afterResponse();
        }

        MenuCache::bump($product->restaurant_id);
    }
}
