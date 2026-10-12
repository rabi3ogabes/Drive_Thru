<?php

use App\Domain\Menu\Actions\BulkPriceUpdate;
use App\Domain\Menu\Actions\DuplicateProduct;
use App\Domain\Menu\Actions\ProcessProductImage;
use App\Domain\Menu\Enums\Allergen;
use App\Domain\Menu\MenuSpreadsheet;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

it('round-trips the menu through Excel without losing anything', function () {
    $restaurant = restaurant();
    $burgers = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Burgers', 'ar' => 'برجر']]);
    Product::factory()->create([
        'restaurant_id' => $restaurant->id, 'menu_category_id' => $burgers->id, 'sku' => 'BRG-1',
        'name' => ['en' => 'Classic', 'ar' => 'كلاسيك'], 'description' => ['en' => 'Smash patty', 'ar' => 'لحم مضغوط'],
        'price_minor' => 2550, 'discount_percent' => 10, 'calories' => 640, 'allergens' => [Allergen::Gluten, Allergen::Dairy],
        'tags' => ['spicy'], 'is_popular' => true,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'menu').'.xlsx';
    $sheet = app(MenuSpreadsheet::class);
    $sheet->export($restaurant, $path);

    $before = Product::withoutTenancy()->get()->map->only(['sku', 'price_minor', 'discount_percent', 'calories', 'is_popular'])->all();

    $parsed = $sheet->parse($restaurant, $path);
    expect($parsed['errors'])->toBe([]);

    $result = $sheet->apply($restaurant, $parsed['rows']);
    expect($result)->toBe(['created' => 0, 'updated' => 1]);

    $product = Product::withoutTenancy()->sole();
    expect(Product::withoutTenancy()->get()->map->only(['sku', 'price_minor', 'discount_percent', 'calories', 'is_popular'])->all())->toBe($before)
        ->and($product->getTranslation('name', 'ar'))->toBe('كلاسيك')
        ->and($product->allergens->map->value->all())->toBe(['gluten', 'dairy'])
        ->and($product->tags->map->value->all())->toBe(['spicy']);

    @unlink($path);
});

it('imports new products and sections from the template, and rejects bad rows all-or-nothing', function () {
    $restaurant = restaurant();
    $sheet = app(MenuSpreadsheet::class);
    $path = tempnam(sys_get_temp_dir(), 'menu').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(MenuSpreadsheet::COLUMNS));
    $writer->addRow(Row::fromValues([null, 'DRK-1', 'Drinks', 'مشروبات', 'Karak', 'كرك', null, null, '3.5', 0, 90, 2, '', '', 0, 1, 0, 1]));
    $writer->addRow(Row::fromValues([null, null, 'Drinks', 'مشروبات', 'Bad', '', null, null, 'free', 120, null, null, 'peanut-butter', '', 0, 0, 0, 1]));
    $writer->close();

    $parsed = $sheet->parse($restaurant, $path);

    expect($parsed['rows'])->toHaveCount(1)
        ->and(collect($parsed['errors'])->pluck('row')->unique()->values()->all())->toBe([3])
        ->and(count($parsed['errors']))->toBeGreaterThanOrEqual(4);

    $sheet->apply($restaurant, $parsed['rows']);
    $karak = Product::withoutTenancy()->sole();

    expect($karak->price_minor)->toBe(350)
        ->and($karak->is_new)->toBeTrue()
        ->and($karak->category?->getTranslation('name', 'ar'))->toBe('مشروبات');

    @unlink($path);
});

it('previews and applies a bulk price change with friendly rounding', function () {
    $restaurant = restaurant();
    $a = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2500]);
    $b = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 1290]);
    $bulk = app(BulkPriceUpdate::class);

    $preview = $bulk->preview([$a, $b], BulkPriceUpdate::PERCENT, 7, roundTo: 50);
    expect($preview->pluck('to')->all())->toBe([2700, 1400]);

    // Nothing changes until applied.
    expect($a->fresh()->price_minor)->toBe(2500);

    expect($bulk->apply($preview))->toBe(2)
        ->and($a->fresh()->price_minor)->toBe(2700)
        ->and($bulk->preview([$b->fresh()], BulkPriceUpdate::FIXED, -2000)->first()['to'])->toBe(0);
});

it('duplicates a combo with its slots and options, switched off for review', function () {
    $restaurant = restaurant();
    $fries = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $meal = Product::factory()->combo()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Classic meal', 'ar' => 'وجبة كلاسيك']]);
    $group = ModifierGroup::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Sauces', 'ar' => 'صلصات']]);
    $meal->modifierGroups()->attach($group->id, ['sort' => 1]);
    $slot = ComboSlot::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_product_id' => $meal->id, 'name' => ['en' => 'Side', 'ar' => 'جانبي']]);
    ComboSlotOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_slot_id' => $slot->id, 'product_id' => $fries->id, 'is_default' => true]);

    $copy = app(DuplicateProduct::class)->handle($meal);

    expect($copy->is_available)->toBeFalse()
        ->and($copy->getTranslation('name', 'en'))->toContain('Classic meal')
        ->and($copy->modifierGroups()->count())->toBe(1)
        ->and($copy->comboSlots()->with('options')->first()?->options->first()?->product_id)->toBe($fries->id);
});

it('creates light WebP and AVIF versions of a product photo', function () {
    Storage::fake('public');
    $restaurant = restaurant();
    $path = UploadedFile::fake()->image('burger.jpg', 1600, 1200)->store('menu/originals', 'public');
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'image_path' => $path]);

    app(ProcessProductImage::class)->handle($product);
    $product->refresh();

    expect($product->image_variants['webp'] ?? [])->toHaveKeys(['400', '800'])
        ->and($product->image_variants['avif'] ?? [])->toHaveKeys(['400', '800'])
        ->and($product->imageUrl(400))->toEndWith('-400.webp');

    Storage::disk('public')->assertExists($product->image_variants['webp']['800']);
});
