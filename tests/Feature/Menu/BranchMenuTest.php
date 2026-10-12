<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Enums\Unavailability;
use App\Domain\Menu\MenuCache;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\MenuSchedule;
use App\Domain\Menu\Models\Product;
use App\Domain\Menu\Notifications\LowStock;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

function menuBranch(?Restaurant $restaurant = null, string $name = 'Lusail'): Branch
{
    return app(CreateBranch::class)->handle($restaurant ?? restaurant(), ['name' => ['en' => $name, 'ar' => $name]]);
}

function menuItem(Restaurant $restaurant, array $attributes = []): Product
{
    return Product::factory()->create(array_merge(['restaurant_id' => $restaurant->id, 'price_minor' => 2500], $attributes));
}

/** Doha wall-clock time (UTC+3). 2026-10-11 is a Sunday. */
function qatar(string $moment): CarbonImmutable
{
    return CarbonImmutable::parse($moment, 'Asia/Qatar');
}

it('prices a product with its discount and a branch override', function () {
    $branch = menuBranch();
    $burger = menuItem($branch->restaurant, ['price_minor' => 3000, 'discount_percent' => 10]);
    $menu = app(BranchMenu::class);

    $offer = $menu->offer($burger, $branch);
    expect($offer->basePriceMinor)->toBe(3000)->and($offer->priceMinor)->toBe(2700)->and($offer->isDiscounted())->toBeTrue();

    $setting = app(BranchStock::class)->setPrice($burger, $branch, 3500);
    expect($menu->offer($burger, $branch, $setting)->priceMinor)->toBe(3150);
});

it('brings an out-of-stock item back at the branch’s local midnight, not UTC midnight', function () {
    $branch = menuBranch();
    $fries = menuItem($branch->restaurant);
    $menu = app(BranchMenu::class);

    $this->travelTo(qatar('2026-10-11 22:15'));
    $setting = app(BranchStock::class)->markOutOfStockToday($fries, $branch);
    expect($menu->offer($fries, $branch, $setting)->unavailable)->toBe(Unavailability::OutOfStockToday);

    // 23:59 in Doha: still out. (That's 20:59 UTC, before UTC midnight too.)
    $this->travelTo(qatar('2026-10-11 23:59'));
    expect($menu->offer($fries, $branch, $setting->fresh())->unavailable)->toBe(Unavailability::OutOfStockToday);

    // 00:00 in Doha (21:00 UTC, long before UTC midnight): back on sale.
    $this->travelTo(qatar('2026-10-12 00:00'));
    expect($menu->offer($fries, $branch, $setting->fresh())->isAvailable())->toBeTrue();
});

it('refills the daily stock count on each local day and stops selling at zero', function () {
    $branch = menuBranch();
    $brownie = menuItem($branch->restaurant);
    $stock = app(BranchStock::class);
    $menu = app(BranchMenu::class);

    $this->travelTo(qatar('2026-10-11 20:00'));
    $stock->setDailyStock($brownie, $branch, 3);

    expect($stock->consume($brownie, $branch, 2))->toBeTrue()
        ->and($stock->consume($brownie, $branch, 2))->toBeFalse()
        ->and($stock->consume($brownie, $branch))->toBeTrue();

    $setting = $menu->settingsFor($branch)->get($brownie->id);
    expect($menu->offer($brownie, $branch, $setting)->unavailable)->toBe(Unavailability::SoldOut);

    $this->travelTo(qatar('2026-10-12 00:01'));
    $offer = $menu->offer($brownie, $branch, $setting->fresh());
    expect($offer->isAvailable())->toBeTrue()->and($offer->stockLeft)->toBe(3);
});

it('alerts the branch team once a day when stock runs low', function () {
    Notification::fake();
    $restaurant = restaurant();
    $branch = menuBranch($restaurant);
    $owner = ownerOf($restaurant);
    $cashier = employeeAt($restaurant, 'cashier');
    $cake = menuItem($restaurant);
    $stock = app(BranchStock::class);

    $this->travelTo(qatar('2026-10-11 12:00'));
    $stock->setDailyStock($cake, $branch, 5, lowThreshold: 2);
    $stock->consume($cake, $branch, 3); // 2 left → alert
    $stock->consume($cake, $branch, 1); // 1 left → already alerted today

    Notification::assertSentToTimes($owner, LowStock::class, 1);
    Notification::assertNotSentTo($cashier, LowStock::class);

    $this->travelTo(qatar('2026-10-12 12:00'));
    $stock->consume($cake, $branch, 4); // new day: 5 → 1, alert again
    Notification::assertSentToTimes($owner, LowStock::class, 2);
});

it('serves breakfast only in the morning and late-night items past midnight', function () {
    $restaurant = restaurant();
    $branch = menuBranch($restaurant);
    $everyDay = range(0, 6);
    $breakfast = MenuSchedule::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Breakfast', 'ar' => 'الفطور'], 'weekdays' => $everyDay, 'starts_at' => '06:00', 'ends_at' => '11:00']);
    $late = MenuSchedule::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Late night', 'ar' => 'آخر الليل'], 'weekdays' => [4, 5], 'starts_at' => '22:00', 'ends_at' => '03:00']);
    $category = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Breakfast', 'ar' => 'الفطور'], 'menu_schedule_id' => $breakfast->id]);
    $omelette = menuItem($restaurant, ['menu_category_id' => $category->id]);
    $midnightSnack = menuItem($restaurant, ['menu_schedule_id' => $late->id]);
    $menu = app(BranchMenu::class);

    $offer = fn (Product $p, string $at) => $menu->offer($p->fresh(['category']), $branch, null, qatar($at))->unavailable;

    expect($offer($omelette, '2026-10-11 07:30'))->toBeNull()
        ->and($offer($omelette, '2026-10-11 11:00'))->toBe(Unavailability::NotNow)
        // Thursday (4) 23:00 and the early hours of Friday belong to Thursday's window.
        ->and($offer($midnightSnack, '2026-10-15 23:00'))->toBeNull()
        ->and($offer($midnightSnack, '2026-10-16 02:30'))->toBeNull()
        ->and($offer($midnightSnack, '2026-10-16 03:00'))->toBe(Unavailability::NotNow)
        // Saturday early hours: Friday's window covers them.
        ->and($offer($midnightSnack, '2026-10-17 01:00'))->toBeNull()
        ->and($offer($midnightSnack, '2026-10-18 01:00'))->toBe(Unavailability::NotNow);
});

it('shows combo savings against buying the default items separately', function () {
    $restaurant = restaurant();
    $branch = menuBranch($restaurant);
    $burger = menuItem($restaurant, ['price_minor' => 2500]);
    $fries = menuItem($restaurant, ['price_minor' => 1000]);
    $drink = menuItem($restaurant, ['price_minor' => 800]);
    $meal = menuItem($restaurant, ['price_minor' => 3600, 'type' => 'combo']);

    foreach ([[$burger], [$fries], [$drink]] as $sort => [$product]) {
        $slot = ComboSlot::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_product_id' => $meal->id, 'name' => ['en' => 'Slot', 'ar' => 'خانة'], 'sort' => $sort]);
        ComboSlotOption::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'combo_slot_id' => $slot->id, 'product_id' => $product->id, 'is_default' => true]);
    }

    // 25 + 10 + 8 = 43 separately, 36 as a meal → save 7.
    expect(app(BranchMenu::class)->comboSavings($meal->fresh(), $branch))->toBe(700);
});

it('builds the guest menu per branch, hiding what a branch doesn’t serve', function () {
    $restaurant = restaurant();
    [$lusail, $wakra] = [menuBranch($restaurant, 'Lusail'), menuBranch($restaurant, 'Wakra')];
    $category = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Burgers', 'ar' => 'برجر']]);
    $classic = menuItem($restaurant, ['menu_category_id' => $category->id, 'name' => ['en' => 'Classic', 'ar' => 'كلاسيك']]);
    $special = menuItem($restaurant, ['menu_category_id' => $category->id, 'name' => ['en' => 'Lusail special', 'ar' => 'خاص']]);
    app(BranchStock::class)->setServed($special, $wakra, false);

    $names = fn (Branch $b) => collect(app(BranchMenu::class)->sections($b))->flatMap(fn ($s) => collect($s['items'])->map(fn ($i) => $i['product']->getTranslation('name', 'en')))->all();

    expect($names($lusail))->toEqualCanonicalizing(['Classic', 'Lusail special'])
        ->and($names($wakra))->toBe(['Classic']);
});

it('invalidates the cached catalog whenever the menu changes', function () {
    $restaurant = restaurant();
    $branch = menuBranch($restaurant);
    $category = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Drinks', 'ar' => 'مشروبات']]);
    $tea = menuItem($restaurant, ['menu_category_id' => $category->id, 'price_minor' => 500]);
    $menu = app(BranchMenu::class);

    $version = MenuCache::version($restaurant->id);
    expect($menu->sections($branch)[0]['items'][0]['offer']->priceMinor)->toBe(500);

    $tea->update(['price_minor' => 700]);

    expect(MenuCache::version($restaurant->id))->toBeGreaterThan($version)
        ->and($menu->sections($branch)[0]['items'][0]['offer']->priceMinor)->toBe(700);
});

it('refuses to mix a product and a branch from different restaurants', function () {
    $product = menuItem(restaurant(['en' => 'A', 'ar' => 'أ']));
    $branch = menuBranch(restaurant(['en' => 'B', 'ar' => 'ب']));

    app(BranchStock::class)->markOutOfStockToday($product, $branch);
})->throws(InvalidArgumentException::class);

it('reads the cached catalog back from a real serializing cache store', function () {
    config(['cache.default' => 'file']);
    Cache::store('file')->flush();

    $restaurant = restaurant();
    $branch = menuBranch($restaurant);
    $category = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Drinks', 'ar' => 'مشروبات']]);
    menuItem($restaurant, ['menu_category_id' => $category->id, 'tags' => ['vegan'], 'allergens' => ['dairy']]);

    app(BranchMenu::class)->sections($branch); // writes the cache
    $sections = app(BranchMenu::class)->sections($branch); // reads it back

    expect($sections[0]['items'][0]['product'])->toBeInstanceOf(Product::class)
        ->and($sections[0]['items'][0]['product']->tags->first()?->value)->toBe('vegan');
});

it('moves sections with nothing to order right now to the end', function () {
    $restaurant = restaurant();
    $branch = menuBranch($restaurant);
    $breakfast = MenuSchedule::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Breakfast', 'ar' => 'الفطور'], 'weekdays' => range(0, 6), 'starts_at' => '06:00', 'ends_at' => '11:00']);
    $morning = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Breakfast', 'ar' => 'الفطور'], 'menu_schedule_id' => $breakfast->id, 'sort' => 1]);
    $burgers = MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Burgers', 'ar' => 'برجر'], 'sort' => 2]);
    menuItem($restaurant, ['menu_category_id' => $morning->id]);
    menuItem($restaurant, ['menu_category_id' => $burgers->id]);

    $order = fn (string $at) => collect(app(BranchMenu::class)->sections($branch, qatar($at)))->map(fn ($s) => $s['category']->getTranslation('name', 'en'))->all();

    expect($order('2026-10-11 08:00'))->toBe(['Breakfast', 'Burgers'])
        ->and($order('2026-10-11 19:00'))->toBe(['Burgers', 'Breakfast']);
});
