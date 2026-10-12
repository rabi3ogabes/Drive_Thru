<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\BranchMenu;
use App\Domain\Menu\Enums\Unavailability;
use App\Domain\Menu\MenuSpreadsheet;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\Product;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\BranchMenuPage;
use App\Filament\App\Resources\Menu\Pages\CreateProduct;
use App\Filament\App\Resources\Menu\Pages\EditProduct;
use App\Filament\App\Resources\Menu\Pages\ListProducts;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

function intoWorkspace(Restaurant $restaurant, User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);
}

function section(Restaurant $restaurant, string $name = 'Burgers'): MenuCategory
{
    return MenuCategory::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => $name, 'ar' => $name]]);
}

it('lets menu managers in and keeps everyone else out of the menu screens', function (string $position, bool $catalog, bool $branchMenu) {
    $restaurant = restaurant(slug: 'masar');
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $user = $position === 'owner' ? ownerOf($restaurant) : employeeAt($restaurant, $position);
    Employee::withoutTenancy()->where('user_id', $user->id)->sole()->branches()->sync([$branch->id]);

    foreach (['menu/items', 'menu/sections', 'menu/options', 'menu/schedules', 'menu/design'] as $page) {
        $response = $this->actingAs($user)->get("/app/masar/{$page}");
        $catalog ? $response->assertOk() : $response->assertForbidden();
    }

    $response = $this->actingAs($user)->get('/app/masar/menu/branches');
    $branchMenu ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => ['owner', true, true],
    'general manager' => ['general_manager', true, true],
    'branch manager' => ['branch_manager', false, true],
    'cashier' => ['cashier', false, false],
    'kitchen' => ['kitchen', false, false],
    'marketing' => ['marketing', false, false],
]);

it('creates an item in both languages with price in minor units and option groups', function () {
    $restaurant = restaurant(slug: 'masar');
    intoWorkspace($restaurant, ownerOf($restaurant));
    $burgers = section($restaurant);
    $sauces = ModifierGroup::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Sauces', 'ar' => 'صلصات']]);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'type' => 'item',
            'name' => ['en' => 'Classic Smash', 'ar' => 'سماش كلاسيك'],
            'menu_category_id' => $burgers->id,
            'price_minor' => '25.50',
            'discount_percent' => 10,
            'allergens' => ['gluten', 'dairy'],
            'tags' => ['spicy'],
            'modifierGroups' => [$sauces->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->sole();
    expect($product->price_minor)->toBe(2550)
        ->and($product->finalPriceMinor())->toBe(2295)
        ->and($product->getTranslation('name', 'ar'))->toBe('سماش كلاسيك')
        ->and($product->modifierGroups->pluck('id')->all())->toBe([$sauces->id]);

    // The editor shows the price back in major units, in both languages.
    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSchemaStateSet(['price_minor' => '25.50', 'name' => ['en' => 'Classic Smash', 'ar' => 'سماش كلاسيك']]);
});

it('updates many prices at once after a preview', function () {
    $restaurant = restaurant(slug: 'masar');
    intoWorkspace($restaurant, ownerOf($restaurant));
    $a = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2000]);
    $b = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 1000]);

    Livewire::test(ListProducts::class)
        ->callAction(TestAction::make('bulkPrice')->table()->bulk(), data: ['mode' => 'percent', 'value' => 10, 'round_to' => '1'], arguments: [])
        ->assertHasNoActionErrors();

    expect($a->fresh()->price_minor)->toBe(2000);

    Livewire::test(ListProducts::class)
        ->selectTableRecords([$a->id, $b->id])
        ->callAction(TestAction::make('bulkPrice')->table()->bulk(), data: ['mode' => 'percent', 'value' => 10, 'round_to' => '1'])
        ->assertHasNoActionErrors();

    expect($a->fresh()->price_minor)->toBe(2200)->and($b->fresh()->price_minor)->toBe(1100);
});

it('lets a branch manager mark an item out of stock at their branch only', function () {
    $restaurant = restaurant(slug: 'masar');
    $mine = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Mine', 'ar' => 'فرعي']]);
    $other = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Other', 'ar' => 'آخر']]);
    $manager = employeeAt($restaurant, 'branch_manager');
    Employee::withoutTenancy()->where('user_id', $manager->id)->sole()->branches()->sync([$mine->id]);
    $fries = Product::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => section($restaurant, 'Sides')->id]);
    intoWorkspace($restaurant, $manager);

    Livewire::test(BranchMenuPage::class)
        ->assertSet('branchId', $mine->id)
        ->callAction(TestAction::make('outOfStock')->table($fries));

    $menu = app(BranchMenu::class);
    expect($menu->offer($fries, $mine, $menu->settingsFor($mine)->get($fries->id))->unavailable)->toBe(Unavailability::OutOfStockToday)
        ->and($menu->offer($fries, $other, $menu->settingsFor($other)->get($fries->id))->isAvailable())->toBeTrue();

    // Switching to a branch they don't work in is refused.
    Livewire::test(BranchMenuPage::class)->call('selectBranch', $other->id)->assertForbidden();
});

it('never shows one restaurant’s menu to another', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار'], slug: 'masar');
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء'], slug: 'sahra');
    Product::factory()->create(['restaurant_id' => $masar->id, 'name' => ['en' => 'Masar Secret', 'ar' => 'سر مسار']]);
    $product = Product::query()->withoutGlobalScopes()->sole();

    $this->actingAs(ownerOf($sahra))->get('/app/masar/menu/items')->assertNotFound();
    $this->actingAs(ownerOf($sahra))->get("/app/sahra/menu/items/{$product->id}/edit")->assertNotFound();
    $this->actingAs(ownerOf($sahra))->get('/app/sahra/menu/items')->assertOk()->assertDontSee('Masar Secret');
});

it('shows the menu on the guest branch page with prices, savings and sold-out items greyed', function () {
    $restaurant = restaurant(slug: 'masar');
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $sides = section($restaurant, 'Sides');
    $fries = Product::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $sides->id, 'name' => ['en' => 'Fries', 'ar' => 'بطاطس'], 'price_minor' => 1000, 'discount_percent' => 20]);
    $rings = Product::factory()->create(['restaurant_id' => $restaurant->id, 'menu_category_id' => $sides->id, 'name' => ['en' => 'Onion rings', 'ar' => 'حلقات البصل']]);
    app(BranchStock::class)->markOutOfStockToday($rings, $branch);

    $this->withHeader('Accept-Language', 'en')->get("/masar/{$branch->code}")
        ->assertOk()
        ->assertSee('Fries')
        ->assertSee('8.00')
        ->assertSee('Onion rings')
        ->assertSee(__('menu.unavailable.out_of_stock', [], 'en'));
});

it('imports a menu from Excel through the menu screen', function () {
    $restaurant = restaurant(slug: 'masar');
    intoWorkspace($restaurant, ownerOf($restaurant));

    $path = tempnam(sys_get_temp_dir(), 'menu').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(MenuSpreadsheet::COLUMNS));
    $writer->addRow(Row::fromValues([null, 'K-1', 'Coffee', 'قهوة', 'Karak', 'كرك', null, null, 5, 0, 160, 2, 'dairy', '', 1, 0, 0, 1]));
    $writer->close();

    Livewire::test(ListProducts::class)
        ->callAction('import', data: ['file' => UploadedFile::fake()->createWithContent('menu.xlsx', (string) file_get_contents($path))])
        ->assertHasNoActionErrors()
        ->assertNotified(__('menu.products.import.done'));

    expect(Product::query()->sole()->price_minor)->toBe(500);
});
