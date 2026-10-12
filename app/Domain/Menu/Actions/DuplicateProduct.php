<?php

declare(strict_types=1);

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copies a product with its options, recommendations and combo slots, so a
 * variant ("Double Classic") starts 90% done. The copy is switched off until
 * the owner reviews it, and branch settings are not copied.
 */
final class DuplicateProduct
{
    public function handle(Product $product, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $actor): Product {
            $product->loadMissing(['modifierGroups', 'recommendations', 'comboSlots.options']);

            $copy = $product->replicate(['sku', 'image_variants']);
            $copy->setTranslations('name', collect($product->getTranslations('name'))
                ->map(fn (string $name, string $locale): string => __('menu.copy_of', ['name' => $name], $locale))
                ->all());
            $copy->is_available = false;
            $copy->sort = $product->sort + 1;
            $copy->save();

            $copy->modifierGroups()->sync($product->modifierGroups->mapWithKeys(fn ($group) => [$group->id => ['sort' => $group->pivot->sort ?? 0]])->all());
            $copy->recommendations()->sync($product->recommendations->mapWithKeys(fn ($rec) => [$rec->id => ['sort' => $rec->pivot->sort ?? 0]])->all());

            foreach ($product->comboSlots as $slot) {
                /** @var ComboSlot $newSlot */
                $newSlot = $copy->comboSlots()->create(['restaurant_id' => $copy->restaurant_id, 'name' => $slot->getTranslations('name'), 'sort' => $slot->sort]);

                foreach ($slot->options as $option) {
                    ComboSlotOption::withoutTenancy()->create([
                        'restaurant_id' => $copy->restaurant_id,
                        'combo_slot_id' => $newSlot->id,
                        'product_id' => $option->product_id,
                        'price_delta_minor' => $option->price_delta_minor,
                        'is_default' => $option->is_default,
                        'sort' => $option->sort,
                    ]);
                }
            }

            activity()->performedOn($copy)->causedBy($actor)->event('menu.duplicated')->withProperties(['from' => $product->id])->log('menu.duplicated');

            return $copy;
        });
    }
}
