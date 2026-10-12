<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Actions\BranchStock;
use App\Domain\Menu\Enums\Allergen as A;
use App\Domain\Menu\Enums\ModifierKind;
use App\Domain\Menu\Enums\ProductTag as T;
use App\Domain\Menu\Enums\ProductType;
use App\Domain\Menu\Models\ComboSlot;
use App\Domain\Menu\Models\ComboSlotOption;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\MenuSchedule;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * A realistic bilingual drive-thru menu: 50 items across breakfast, burgers,
 * chicken, meals, sides, drinks, coffee, desserts and kids, with reusable
 * option groups, meals with savings, a breakfast schedule and some
 * branch-specific stock so every screen has something to show.
 */
final class DemoMenuSeeder
{
    private Restaurant $restaurant;

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, ModifierGroup> */
    private array $groups = [];

    public function run(Restaurant $restaurant): void
    {
        if (Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->exists()) {
            return;
        }

        $this->restaurant = $restaurant;

        $breakfast = $this->schedule(['en' => 'Breakfast', 'ar' => 'الفطور'], range(0, 6), '06:00', '11:00');
        $this->optionGroups();

        $sections = [
            'breakfast' => $this->section('🍳', 'Breakfast', 'الفطور', 'Until 11 am, every day.', 'حتى الساعة 11 صباحًا يوميًا.', $breakfast),
            'burgers' => $this->section('🍔', 'Burgers', 'البرجر', 'Smashed to order on a hot griddle.', 'تُضغط عند الطلب على صاج ساخن.'),
            'chicken' => $this->section('🍗', 'Chicken', 'الدجاج', 'Crispy, grilled or spicy.', 'مقرمش أو مشوي أو حار.'),
            'meals' => $this->section('🍱', 'Meals', 'الوجبات', 'Main, side and drink. Always a better deal.', 'طبق رئيسي وجانبي ومشروب، دائمًا بسعر أوفر.'),
            'sides' => $this->section('🍟', 'Sides', 'الأطباق الجانبية', null, null),
            'drinks' => $this->section('🥤', 'Cold drinks', 'المشروبات الباردة', null, null),
            'coffee' => $this->section('☕', 'Coffee & tea', 'القهوة والشاي', null, null),
            'desserts' => $this->section('🍰', 'Desserts', 'الحلويات', null, null),
            'kids' => $this->section('🧒', 'Kids', 'الأطفال', null, null),
        ];

        $s = fn (string $key): int => $sections[$key]->id;

        // key, section, en, ar, price, kcal, allergens, tags, flags, groups, description en, ar
        $items = [
            ['egg_muffin', 'breakfast', 'Egg & Cheese Muffin', 'مافن البيض والجبن', 12, 410, [A::Gluten, A::Eggs, A::Dairy], [], [], [], 'Free-range egg, cheddar and a toasted muffin.', 'بيض بلدي وجبن شيدر في مافن محمّص.'],
            ['halloumi_wrap', 'breakfast', 'Halloumi Wrap', 'راب الحلوم', 15, 480, [A::Gluten, A::Dairy], [T::Vegetarian], ['popular'], [], 'Grilled halloumi, tomato, olives and mint.', 'حلوم مشوي وطماطم وزيتون ونعناع.'],
            ['shakshuka', 'breakfast', 'Shakshuka Box', 'علبة الشكشوكة', 18, 520, [A::Eggs, A::Gluten], [T::Vegetarian], [], [], 'Eggs in spiced tomato sauce with warm bread.', 'بيض بصلصة الطماطم المتبلة مع خبز دافئ.'],
            ['zaatar', 'breakfast', 'Zaatar Manakish', 'مناقيش زعتر', 8, 360, [A::Gluten, A::Sesame], [T::Vegan], [], [], 'Fresh from the oven with olive oil.', 'طازجة من الفرن بزيت الزيتون.'],
            ['platter', 'breakfast', 'Breakfast Platter', 'صحن الفطور', 22, 690, [A::Eggs, A::Dairy, A::Gluten], [T::HighProtein], ['new'], [], 'Eggs, halloumi, labneh, olives and bread.', 'بيض وحلوم ولبنة وزيتون وخبز.'],

            ['classic', 'burgers', 'Classic Smash', 'سماش كلاسيك', 25, 640, [A::Gluten, A::Dairy, A::Sesame], [], ['popular'], ['extras', 'remove', 'sauces'], 'Two smashed patties, cheddar, pickles and house sauce.', 'قطعتا لحم مضغوطتان وجبن شيدر ومخلل وصلصة البيت.'],
            ['double', 'burgers', 'Double Smash', 'دبل سماش', 33, 920, [A::Gluten, A::Dairy, A::Sesame], [T::HighProtein], [], ['extras', 'remove', 'sauces'], 'Four patties for serious appetites.', 'أربع قطع لحم للشهية الكبيرة.'],
            ['truffle', 'burgers', 'Truffle Mushroom', 'ترافل ومشروم', 32, 760, [A::Gluten, A::Dairy, A::Eggs], [], ['new'], ['extras', 'remove'], 'Sautéed mushrooms, Swiss cheese and truffle mayo.', 'فطر سوتيه وجبن سويسري ومايونيز الترافل.'],
            ['jalapeno', 'burgers', 'Spicy Jalapeño', 'هالابينو حار', 28, 700, [A::Gluten, A::Dairy], [T::Spicy], [], ['extras', 'remove', 'spice'], 'Pepper jack, jalapeños and chipotle sauce.', 'جبن بيبر جاك وهالابينو وصلصة تشيبوتلي.'],
            ['veggie', 'burgers', 'Halloumi Veggie Burger', 'برجر الحلوم النباتي', 26, 590, [A::Gluten, A::Dairy], [T::Vegetarian], [], ['remove', 'sauces'], 'Grilled halloumi, roasted pepper and rocket.', 'حلوم مشوي وفلفل مشوي وجرجير.'],
            ['brisket', 'burgers', 'BBQ Brisket', 'بريسكت باربكيو', 36, 880, [A::Gluten, A::Mustard], [], ['limited'], ['extras', 'remove'], '12-hour smoked brisket with BBQ glaze.', 'بريسكت مدخن 12 ساعة بصلصة الباربكيو.'],

            ['crispy', 'chicken', 'Crispy Chicken Sandwich', 'ساندويتش الدجاج المقرمش', 24, 610, [A::Gluten, A::Eggs], [], ['popular'], ['remove', 'sauces'], 'Buttermilk-fried thigh, slaw and pickles.', 'فخذ دجاج مقلي بالحليب مع كول سلو ومخلل.'],
            ['zinger', 'chicken', 'Spicy Zinger', 'زنجر حار', 25, 640, [A::Gluten, A::Eggs], [T::Spicy], [], ['remove', 'spice'], 'Our crispiest chicken with a slow-burn kick.', 'أكثر دجاجنا قرمشة بحرارة متدرجة.'],
            ['grilled_wrap', 'chicken', 'Grilled Chicken Wrap', 'راب الدجاج المشوي', 22, 450, [A::Gluten, A::Dairy], [T::Healthy, T::HighProtein], [], ['remove', 'sauces'], 'Chargrilled chicken, garlic yoghurt and greens.', 'دجاج مشوي على الفحم ولبن بالثوم وخضار.'],
            ['tenders', 'chicken', 'Chicken Tenders (4 pcs)', 'تندر الدجاج (4 قطع)', 21, 520, [A::Gluten, A::Eggs], [], [], ['sauces'], 'Hand-breaded strips with your choice of sauce.', 'شرائح مغطاة يدويًا مع صلصة من اختيارك.'],
            ['wings', 'chicken', 'Hot Wings (8 pcs)', 'أجنحة حارة (8 قطع)', 27, 690, [A::Celery], [T::Spicy], [], ['spice', 'sauces'], 'Crispy wings tossed in buffalo sauce.', 'أجنحة مقرمشة بصلصة البافلو.'],
            ['shawarma', 'chicken', 'Chicken Shawarma Wrap', 'شاورما دجاج', 16, 520, [A::Gluten, A::Sesame], [], ['popular'], ['remove'], 'Garlic toum, pickles and fries inside.', 'ثومية ومخلل وبطاطس بالداخل.'],

            ['fries', 'sides', 'Fries', 'بطاطس مقلية', 10, 340, [], [T::Vegan], ['popular'], ['fries_size'], 'Skin-on, double-fried, sea salt.', 'بقشرها، مقلية مرتين، بملح البحر.'],
            ['cheese_fries', 'sides', 'Cheese Fries', 'بطاطس بالجبن', 15, 520, [A::Dairy], [T::Vegetarian], [], [], 'Fries under warm cheddar sauce.', 'بطاطس مغطاة بصلصة الشيدر الدافئة.'],
            ['loaded', 'sides', 'Loaded Fries', 'بطاطس محمّلة', 19, 690, [A::Dairy], [T::Spicy], ['new'], [], 'Cheese, jalapeños and crispy beef bits.', 'جبن وهالابينو وقطع لحم مقرمشة.'],
            ['rings', 'sides', 'Onion Rings', 'حلقات البصل', 13, 410, [A::Gluten], [T::Vegetarian], [], [], 'Beer-free crispy batter.', 'عجينة مقرمشة.'],
            ['sweet_potato', 'sides', 'Sweet Potato Fries', 'بطاطا حلوة مقلية', 14, 380, [], [T::Vegan], [], [], 'With smoked paprika salt.', 'مع ملح البابريكا المدخنة.'],
            ['coleslaw', 'sides', 'Coleslaw', 'كول سلو', 8, 190, [A::Eggs, A::Mustard], [T::Vegetarian], [], [], null, null],
            ['hummus', 'sides', 'Hummus Cup', 'كوب حمص', 9, 220, [A::Sesame], [T::Vegan, T::Healthy], [], [], null, null],

            ['cola', 'drinks', 'Cola', 'كولا', 8, 140, [], [], [], ['drink_size'], null, null],
            ['diet_cola', 'drinks', 'Diet Cola', 'كولا دايت', 8, 1, [], [], [], ['drink_size'], null, null],
            ['lemon_mint', 'drinks', 'Lemon Mint', 'ليمون بالنعناع', 14, 160, [], [T::Vegan], ['popular'], ['drink_size'], 'Fresh lemon blended with mint and ice.', 'ليمون طازج مخلوط بالنعناع والثلج.'],
            ['orange', 'drinks', 'Fresh Orange Juice', 'عصير برتقال طازج', 15, 150, [], [T::Vegan, T::Healthy], [], ['drink_size'], null, null],
            ['water', 'drinks', 'Water', 'ماء', 4, 0, [], [], [], [], null, null],
            ['laban', 'drinks', 'Laban', 'لبن', 6, 120, [A::Dairy], [], [], [], null, null],
            ['mango', 'drinks', 'Mango Smoothie', 'سموذي المانجو', 18, 290, [A::Dairy], [], ['new'], [], 'Alphonso mango, yoghurt and honey.', 'مانجو ألفونسو ولبن وعسل.'],

            ['karak', 'coffee', 'Karak Tea', 'شاي كرك', 5, 160, [A::Dairy], [], ['popular'], ['sugar'], 'Strong spiced tea, slow-brewed with milk.', 'شاي متبل قوي مطبوخ ببطء مع الحليب.'],
            ['espresso', 'coffee', 'Espresso', 'إسبريسو', 12, 5, [], [T::Vegan], [], ['sugar'], null, null],
            ['spanish', 'coffee', 'Spanish Latte', 'سبانش لاتيه', 19, 240, [A::Dairy], [], ['popular'], ['milk', 'sugar'], 'Espresso with sweetened condensed milk.', 'إسبريسو مع حليب مكثف محلى.'],
            ['iced_latte', 'coffee', 'Iced Latte', 'آيس لاتيه', 18, 180, [A::Dairy], [], [], ['milk', 'sugar'], null, null],
            ['cappuccino', 'coffee', 'Cappuccino', 'كابتشينو', 17, 150, [A::Dairy], [], [], ['milk', 'sugar'], null, null],
            ['arabic_coffee', 'coffee', 'Arabic Coffee & Dates', 'قهوة عربية وتمر', 14, 110, [], [T::Vegan], [], [], 'Cardamom gahwa with three Khalas dates.', 'قهوة بالهيل مع ثلاث تمرات خلاص.'],

            ['brownie', 'desserts', 'Chocolate Brownie', 'براونيز الشوكولاتة', 14, 420, [A::Gluten, A::Eggs, A::Dairy], [], [], [], null, null],
            ['kunafa', 'desserts', 'Pistachio Kunafa', 'كنافة بالفستق', 18, 540, [A::Gluten, A::Dairy, A::Nuts], [], ['new'], [], 'Crisp kunafa, cream and Aleppo pistachio.', 'كنافة مقرمشة وقشطة وفستق حلبي.'],
            ['cone', 'desserts', 'Soft Serve Cone', 'آيس كريم كون', 7, 210, [A::Dairy, A::Gluten], [], [], [], null, null],
            ['date_cake', 'desserts', 'Sticky Date Cake', 'كيكة التمر', 15, 470, [A::Gluten, A::Eggs, A::Dairy], [], [], [], null, null],
            ['saffron', 'desserts', 'Saffron Milk Cake', 'كيكة الحليب بالزعفران', 19, 450, [A::Gluten, A::Eggs, A::Dairy], [], ['limited'], [], null, null],

            ['kids_burger', 'kids', 'Kids Burger', 'برجر الأطفال', 14, 390, [A::Gluten, A::Dairy], [T::Kids], [], ['remove'], null, null],
            ['kids_nuggets', 'kids', 'Kids Nuggets', 'ناغتس الأطفال', 13, 360, [A::Gluten, A::Eggs], [T::Kids], [], ['sauces'], null, null],
        ];

        $sort = 0;
        foreach ($items as [$key, $section, $en, $ar, $price, $kcal, $allergens, $tags, $flags, $groups, $descEn, $descAr]) {
            $product = Product::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'menu_category_id' => $s($section),
                'sku' => strtoupper(substr($section, 0, 3)).'-'.str_pad((string) ++$sort, 3, '0', STR_PAD_LEFT),
                'name' => ['en' => $en, 'ar' => $ar],
                'description' => $descEn !== null ? ['en' => $descEn, 'ar' => $descAr] : null,
                'price_minor' => $price * 100,
                'calories' => $kcal,
                'prep_minutes' => in_array($section, ['drinks', 'coffee', 'desserts'], true) ? 2 : null,
                'allergens' => $allergens,
                'tags' => $tags,
                'is_popular' => in_array('popular', $flags, true),
                'is_new' => in_array('new', $flags, true),
                'is_limited' => in_array('limited', $flags, true),
                'sort' => $sort,
            ]);

            $product->modifierGroups()->sync(collect($groups)->mapWithKeys(fn (string $g, int $i): array => [$this->groups[$g]->id => ['sort' => $i]])->all());
            $this->products[$key] = $product;
        }

        // A weekend 10% off on the loaded fries shows the discount display.
        $this->products['loaded']->update(['discount_percent' => 10]);

        $this->meals($s('meals'), $s('kids'));
        $this->recommendations();
        $this->branchStock($restaurant);
    }

    private function meals(int $mealsSection, int $kidsSection): void
    {
        $sides = [['fries', 0, true], ['rings', 300, false], ['sweet_potato', 400, false], ['cheese_fries', 500, false]];
        $drinks = [['cola', 0, true], ['diet_cola', 0, false], ['water', 0, false], ['lemon_mint', 500, false]];

        $meals = [
            ['classic_meal', 'Classic Smash Meal', 'وجبة سماش كلاسيك', 36, 'classic', $mealsSection, $sides, $drinks, ['popular']],
            ['double_meal', 'Double Smash Meal', 'وجبة دبل سماش', 44, 'double', $mealsSection, $sides, $drinks, []],
            ['crispy_meal', 'Crispy Chicken Meal', 'وجبة الدجاج المقرمش', 35, 'crispy', $mealsSection, $sides, $drinks, ['popular']],
            ['zinger_meal', 'Zinger Meal', 'وجبة زنجر', 36, 'zinger', $mealsSection, $sides, $drinks, []],
            ['tenders_meal', 'Tenders Meal', 'وجبة التندر', 32, 'tenders', $mealsSection, $sides, $drinks, []],
            ['kids_meal', 'Kids Meal', 'وجبة الأطفال', 19, 'kids_burger', $kidsSection, [['fries', 0, true], ['coleslaw', 0, false]], [['laban', 0, true], ['water', 0, false], ['orange', 300, false]], []],
        ];

        foreach ($meals as [$key, $en, $ar, $price, $main, $section, $sideOptions, $drinkOptions, $flags]) {
            $meal = Product::withoutTenancy()->create([
                'restaurant_id' => $this->restaurant->id,
                'menu_category_id' => $section,
                'type' => ProductType::Combo,
                'sku' => 'MEAL-'.strtoupper(substr($key, 0, 6)),
                'name' => ['en' => $en, 'ar' => $ar],
                'description' => ['en' => 'With a side and a drink of your choice.', 'ar' => 'مع طبق جانبي ومشروب من اختيارك.'],
                'price_minor' => $price * 100,
                'tags' => $key === 'kids_meal' ? [T::Kids] : [],
                'is_popular' => in_array('popular', $flags, true),
                'sort' => 100 + count($this->products),
            ]);

            $mains = $key === 'kids_meal' ? [[$main, 0, true], ['kids_nuggets', 0, false]] : [[$main, 0, true]];

            foreach ([['Main', 'الطبق الرئيسي', $mains], ['Side', 'الطبق الجانبي', $sideOptions], ['Drink', 'المشروب', $drinkOptions]] as $slotSort => [$slotEn, $slotAr, $options]) {
                $slot = ComboSlot::withoutTenancy()->create([
                    'restaurant_id' => $this->restaurant->id,
                    'combo_product_id' => $meal->id,
                    'name' => ['en' => $slotEn, 'ar' => $slotAr],
                    'sort' => $slotSort,
                ]);

                foreach ($options as $optionSort => [$productKey, $delta, $default]) {
                    ComboSlotOption::withoutTenancy()->create([
                        'restaurant_id' => $this->restaurant->id,
                        'combo_slot_id' => $slot->id,
                        'product_id' => $this->products[$productKey]->id,
                        'price_delta_minor' => $delta,
                        'is_default' => $default,
                        'sort' => $optionSort,
                    ]);
                }
            }

            $this->products[$key] = $meal;

            if ($key !== 'kids_meal') {
                $this->products[$main]->update(['meal_product_id' => $meal->id]);
            }
        }
    }

    private function recommendations(): void
    {
        foreach ([
            'classic' => ['fries', 'lemon_mint', 'brownie'],
            'double' => ['loaded', 'cola'],
            'crispy' => ['coleslaw', 'lemon_mint'],
            'karak' => ['zaatar', 'date_cake'],
            'spanish' => ['brownie', 'kunafa'],
        ] as $product => $recommended) {
            $this->products[$product]->recommendations()->sync(collect($recommended)->mapWithKeys(fn (string $k, int $i): array => [$this->products[$k]->id => ['sort' => $i]])->all());
        }
    }

    private function branchStock(Restaurant $restaurant): void
    {
        $branches = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->get()->keyBy('code');
        $stock = app(BranchStock::class);

        if ($branch = $branches->get('al-wakra')) {
            $stock->setServed($this->products['truffle'], $branch, false);
        }

        if ($branch = $branches->get('west-bay')) {
            $stock->setDailyStock($this->products['kunafa'], $branch, 20, lowThreshold: 5);
            $stock->setDailyStock($this->products['brisket'], $branch, 12, lowThreshold: 3);
            $stock->setPrice($this->products['spanish'], $branch, 2100);
        }

        if ($branch = $branches->get('lusail-marina')) {
            $stock->markOutOfStockToday($this->products['saffron'], $branch);
        }
    }

    private function optionGroups(): void
    {
        $groups = [
            'extras' => [['en' => 'Extras', 'ar' => 'إضافات'], ModifierKind::Choice, 0, null, [
                ['Extra cheese', 'جبن إضافي', 300, 90], ['Beef bacon', 'لحم مقدد بقري', 500, 120], ['Extra patty', 'قطعة لحم إضافية', 800, 230], ['Jalapeños', 'هالابينو', 200, 5], ['Caramelised onions', 'بصل مكرمل', 200, 40],
            ]],
            'remove' => [['en' => 'Remove ingredients', 'ar' => 'إزالة مكونات'], ModifierKind::Remove, 0, null, [
                ['No onions', 'بدون بصل', 0, null], ['No pickles', 'بدون مخلل', 0, null], ['No tomato', 'بدون طماطم', 0, null], ['No lettuce', 'بدون خس', 0, null], ['No sauce', 'بدون صلصة', 0, null],
            ]],
            'sauces' => [['en' => 'Dipping sauces', 'ar' => 'صلصات التغميس'], ModifierKind::Choice, 0, 2, [
                ['House sauce', 'صلصة البيت', 0, 90], ['Garlic toum', 'ثومية', 0, 110], ['BBQ', 'باربكيو', 0, 60], ['Spicy mayo', 'مايونيز حار', 0, 100], ['Honey mustard', 'خردل بالعسل', 0, 80],
            ]],
            'spice' => [['en' => 'Spice level', 'ar' => 'درجة الحرارة'], ModifierKind::Choice, 1, 1, [
                ['Mild', 'خفيف', 0, null, true], ['Medium', 'متوسط', 0, null], ['Hot', 'حار', 0, null], ['Extra hot', 'حار جدًا', 0, null],
            ]],
            'drink_size' => [['en' => 'Size', 'ar' => 'الحجم'], ModifierKind::Choice, 1, 1, [
                ['Regular', 'عادي', 0, null, true], ['Large', 'كبير', 300, 60],
            ]],
            'fries_size' => [['en' => 'Fries size', 'ar' => 'حجم البطاطس'], ModifierKind::Choice, 1, 1, [
                ['Regular', 'عادي', 0, null, true], ['Large', 'كبير', 400, 160],
            ]],
            'milk' => [['en' => 'Milk', 'ar' => 'الحليب'], ModifierKind::Choice, 1, 1, [
                ['Full fat', 'كامل الدسم', 0, null, true], ['Skimmed', 'خالي الدسم', 0, -30], ['Oat', 'شوفان', 200, 20], ['Almond', 'لوز', 200, -20],
            ]],
            'sugar' => [['en' => 'Sugar', 'ar' => 'السكر'], ModifierKind::Choice, 1, 1, [
                ['No sugar', 'بدون سكر', 0, null], ['Less sugar', 'سكر خفيف', 0, null], ['Regular', 'عادي', 0, null, true],
            ]],
        ];

        $sort = 0;
        foreach ($groups as $key => [$name, $kind, $min, $max, $options]) {
            $group = ModifierGroup::withoutTenancy()->create([
                'restaurant_id' => $this->restaurant->id,
                'name' => $name,
                'kind' => $kind,
                'min_select' => $min,
                'max_select' => $max,
                'sort' => $sort++,
            ]);

            foreach ($options as $i => $option) {
                ModifierOption::withoutTenancy()->create([
                    'restaurant_id' => $this->restaurant->id,
                    'modifier_group_id' => $group->id,
                    'name' => ['en' => $option[0], 'ar' => $option[1]],
                    'price_delta_minor' => $option[2],
                    'calories_delta' => $option[3],
                    'is_default' => $option[4] ?? false,
                    'sort' => $i,
                ]);
            }

            $this->groups[$key] = $group;
        }
    }

    /**
     * @param  list<int>  $weekdays
     * @param  array{en: string, ar: string}  $name
     */
    private function schedule(array $name, array $weekdays, string $from, string $to): MenuSchedule
    {
        return MenuSchedule::withoutTenancy()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => $name,
            'weekdays' => $weekdays,
            'starts_at' => $from,
            'ends_at' => $to,
        ]);
    }

    private function section(string $icon, string $en, string $ar, ?string $descEn, ?string $descAr, ?MenuSchedule $schedule = null): MenuCategory
    {
        return MenuCategory::withoutTenancy()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => ['en' => $en, 'ar' => $ar],
            'description' => $descEn !== null ? ['en' => $descEn, 'ar' => $descAr] : null,
            'icon' => $icon,
            'menu_schedule_id' => $schedule?->id,
            'sort' => (int) MenuCategory::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->max('sort') + 1,
        ]);
    }
}
