<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Branches and their menus as served right now (price, availability and
 * why not), and switching a product on or off at a branch: the same actions
 * the branch team uses, so guests see the change at once.
 */
final class MenuController
{
    public function branches(): JsonResponse
    {
        $branches = Branch::query()->orderBy('id')->get()->map(fn (Branch $branch): array => [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->getTranslations('name'),
            'city' => $branch->getTranslations('city'),
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
        ]);

        return response()->json(['data' => $branches]);
    }

    public function menu(string $branch, BranchMenu $menu): JsonResponse
    {
        $model = $this->branch($branch);
        $settings = $menu->settingsFor($model);

        $categories = $menu->catalog($model->restaurant)->map(fn (MenuCategory $category): array => [
            'id' => $category->id,
            'name' => $category->getTranslations('name'),
            'visible' => $category->is_visible,
            'products' => $category->products->map(function (Product $product) use ($menu, $model, $settings): array {
                $offer = $menu->offer($product, $model, $settings->get($product->id));

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->getTranslations('name'),
                    'type' => $product->type->value,
                    'price_minor' => $offer->priceMinor,
                    'base_price_minor' => $offer->basePriceMinor,
                    'available' => $offer->isAvailable(),
                    'unavailable_reason' => $offer->unavailable?->value,
                    'stock_left' => $offer->stockLeft,
                    'station' => $product->station?->value,
                ];
            })->values(),
        ])->values();

        return response()->json(['data' => ['branch' => $model->code, 'currency' => $model->restaurant->currency, 'categories' => $categories]]);
    }

    public function availability(Request $request, string $branch, int $product, BranchStock $stock, BranchMenu $menu): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'out_of_stock_today', 'not_served'])],
        ]);

        $model = $this->branch($branch);
        $item = Product::query()->findOrFail($product);

        match ($data['status']) {
            'out_of_stock_today' => $stock->markOutOfStockToday($item, $model),
            'not_served' => $stock->setServed($item, $model, false),
            default => [$stock->setServed($item, $model, true), $stock->backInStock($item, $model)],
        };

        $offer = $menu->offer($item, $model, $menu->settingsFor($model)->get($item->id));

        return response()->json(['data' => [
            'product_id' => $item->id,
            'branch' => $model->code,
            'available' => $offer->isAvailable(),
            'unavailable_reason' => $offer->unavailable?->value,
        ]]);
    }

    private function branch(string $code): Branch
    {
        return Branch::query()->with('restaurant')->where('code', $code)->firstOrFail();
    }
}
