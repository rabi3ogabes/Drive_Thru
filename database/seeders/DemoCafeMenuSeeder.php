<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Menu\Actions\ProcessProductImage;
use App\Domain\Menu\Enums\Allergen as A;
use App\Domain\Menu\Enums\ModifierKind;
use App\Domain\Menu\Enums\ProductTag as T;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\ModifierGroup;
use App\Domain\Menu\Models\ModifierOption;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Storage;

/**
 * A photographed café menu for Karak House: karak and coffee, cold drinks,
 * manakish and sandwiches, and Arabic sweets. Photos are Pexels images
 * (Pexels License: free to use, no attribution required) kept in
 * database/seeders/images/karak and served from this app's own storage.
 */
final class DemoCafeMenuSeeder
{
    private Restaurant $restaurant;

    public function run(Restaurant $restaurant): void
    {
        if (Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->exists()) {
            return;
        }

        $this->restaurant = $restaurant;
        $groups = $this->groups();

        $sections = [
            'karak' => $this->section('🫖', 'Karak & tea', 'الكرك والشاي', 'Slow-brewed every hour, never from a powder.', 'يُطبخ ببطء كل ساعة، لا بودرة أبدًا.'),
            'coffee' => $this->section('☕', 'Coffee', 'القهوة', 'Speciality beans, roasted in Doha.', 'حبوب مختصة محمّصة في الدوحة.'),
            'cold' => $this->section('🧊', 'Cold drinks', 'المشروبات الباردة', null, null),
            'bakery' => $this->section('🥙', 'Manakish & sandwiches', 'المناقيش والساندويتشات', 'Baked to order in our stone oven.', 'تُخبز عند الطلب في فرننا الحجري.'),
            'sweets' => $this->section('🍯', 'Arabic sweets', 'الحلويات العربية', null, null),
        ];

        // key (photo), section, en, ar, price (QAR), kcal, allergens, tags, flags, option groups, description en, ar
        $items = [
            ['karak-tea', 'karak', 'Karak Tea', 'شاي كرك', 3, 160, [A::Dairy], [], ['popular'], ['sugar'], 'Strong black tea, cardamom and evaporated milk, simmered slowly.', 'شاي أسود قوي وهيل وحليب مبخر، يُطبخ على نار هادئة.'],
            ['saffron-karak', 'karak', 'Saffron Karak', 'كرك بالزعفران', 6, 190, [A::Dairy], [], ['new'], ['sugar'], 'Our karak finished with Iranian saffron and a soft milk foam.', 'كركنا مع زعفران إيراني ورغوة حليب ناعمة.'],
            ['arabic-coffee', 'karak', 'Arabic Coffee', 'قهوة عربية', 9, 10, [], [T::Vegan], [], [], 'Light-roast gahwa with cardamom and saffron, served with dates.', 'قهوة شقراء بالهيل والزعفران، تُقدَّم مع التمر.'],
            ['spanish-latte', 'coffee', 'Spanish Latte', 'سبانيش لاتيه', 18, 260, [A::Dairy], [], ['popular'], ['milk', 'size'], 'Double espresso, condensed milk and silky steamed milk.', 'إسبريسو مزدوج وحليب مكثف وحليب مبخر ناعم.'],
            ['cappuccino', 'coffee', 'Cappuccino', 'كابتشينو', 16, 130, [A::Dairy], [], [], ['milk', 'size'], 'Equal parts espresso, milk and velvet foam.', 'إسبريسو وحليب ورغوة مخملية بأجزاء متساوية.'],
            ['matcha-latte', 'coffee', 'Matcha Latte', 'ماتشا لاتيه', 20, 180, [A::Dairy], [T::Vegetarian], [], ['milk', 'size'], 'Ceremonial-grade matcha whisked to order.', 'ماتشا فاخرة تُخفق عند الطلب.'],
            ['iced-karak', 'cold', 'Iced Karak', 'كرك مثلج', 8, 210, [A::Dairy], [], ['popular'], ['sugar'], 'Chilled karak over ice, as smooth as the hot one.', 'كرك مبرد على الثلج، بنعومة الكرك الساخن.'],
            ['iced-spanish-latte', 'cold', 'Iced Spanish Latte', 'سبانيش لاتيه مثلج', 19, 280, [A::Dairy], [], [], ['milk', 'size'], 'Layered over ice: condensed milk, cold milk and espresso.', 'طبقات على الثلج: حليب مكثف وحليب بارد وإسبريسو.'],
            ['mint-lemonade', 'cold', 'Mint Lemonade', 'ليمون بالنعناع', 14, 120, [], [T::Vegan], [], ['size'], 'Fresh lemons blended with mint and crushed ice.', 'ليمون طازج مخفوق مع النعناع والثلج المجروش.'],
            ['zaatar-manakish', 'bakery', 'Zaatar Manakish', 'مناقيش زعتر', 8, 360, [A::Gluten, A::Sesame], [T::Vegan], ['popular'], [], 'Wild thyme, sumac, sesame and olive oil on fresh dough.', 'زعتر بري وسماق وسمسم وزيت زيتون على عجينة طازجة.'],
            ['cheese-manakish', 'bakery', 'Cheese Manakish', 'مناقيش جبن', 12, 480, [A::Gluten, A::Dairy], [T::Vegetarian], [], [], 'Akkawi and mozzarella, baked until golden.', 'جبن عكاوي وموزاريلا، تُخبز حتى تتحمّر.'],
            ['halloumi-toast', 'bakery', 'Halloumi & Avocado Toast', 'توست الحلوم والأفوكادو', 24, 520, [A::Gluten, A::Dairy], [T::Vegetarian], ['new'], [], 'Grilled halloumi, smashed avocado and chilli on sourdough.', 'حلوم مشوي وأفوكادو مهروس وفلفل على خبز العجين المخمر.'],
            ['egg-brioche', 'bakery', 'Egg Brioche', 'بريوش البيض', 18, 540, [A::Gluten, A::Eggs, A::Dairy], [], [], [], 'A sunny-side egg, cheddar and chive mayo in toasted brioche.', 'بيضة عيون وجبن شيدر ومايونيز بالثوم المعمّر في بريوش محمّص.'],
            ['falafel-wrap', 'bakery', 'Falafel Wrap', 'راب الفلافل', 15, 560, [A::Gluten, A::Sesame], [T::Vegan], [], [], 'Crisp falafel, tahini, pickles and greens in warm saj bread.', 'فلافل مقرمشة وطحينة ومخلل وخضار في خبز صاج دافئ.'],
            ['luqaimat', 'sweets', 'Luqaimat', 'لقيمات', 12, 420, [A::Gluten], [T::Vegetarian], ['popular'], [], 'Golden dumplings with date syrup and toasted sesame.', 'لقيمات ذهبية بدبس التمر والسمسم المحمّص.'],
            ['kunafa', 'sweets', 'Pistachio Kunafa', 'كنافة بالفستق', 18, 540, [A::Gluten, A::Dairy, A::Nuts], [], [], [], 'Crisp kunafa, sweet cheese and Aleppo pistachio.', 'كنافة مقرمشة وجبن حلو وفستق حلبي.'],
            ['date-cake', 'sweets', 'Sticky Date Cake', 'كيكة التمر', 15, 470, [A::Gluten, A::Eggs, A::Dairy], [], ['limited'], [], 'Warm Khalas date sponge with toffee sauce.', 'كيكة تمر خلاص دافئة بصلصة التوفي.'],
        ];

        $images = app(ProcessProductImage::class);

        foreach ($items as $sort => [$photo, $section, $en, $ar, $price, $kcal, $allergens, $tags, $flags, $options, $descEn, $descAr]) {
            $product = Product::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'menu_category_id' => $sections[$section]->id,
                'sku' => strtoupper(substr($section, 0, 3)).'-'.str_pad((string) ($sort + 1), 3, '0', STR_PAD_LEFT),
                'name' => ['en' => $en, 'ar' => $ar],
                'description' => ['en' => $descEn, 'ar' => $descAr],
                'price_minor' => $price * 100,
                'calories' => $kcal,
                'prep_minutes' => in_array($section, ['karak', 'coffee', 'cold', 'sweets'], true) ? 2 : 6,
                'allergens' => $allergens,
                'tags' => $tags,
                'is_popular' => in_array('popular', $flags, true),
                'is_new' => in_array('new', $flags, true),
                'is_limited' => in_array('limited', $flags, true),
                'sort' => $sort + 1,
            ]);

            $product->modifierGroups()->sync(collect($options)->mapWithKeys(fn (string $g, int $i): array => [$groups[$g]->id => ['sort' => $i]])->all());
            $this->photo($product, $photo, $images);
        }
    }

    /** Copies the seeder photo into the app's storage and builds its WebP/AVIF sizes. */
    public function photo(Product $product, string $photo, ProcessProductImage $images): void
    {
        $source = database_path("seeders/images/karak/{$photo}.jpg");

        if (! is_file($source)) {
            return;
        }

        $path = "menu/{$product->restaurant_id}/{$product->id}/{$photo}.jpg";
        Storage::disk('public')->put($path, (string) file_get_contents($source));
        $product->forceFill(['image_path' => $path])->saveQuietly();
        $images->handle($product->fresh() ?? $product);
    }

    /**
     * @return array<string, ModifierGroup>
     */
    private function groups(): array
    {
        $definitions = [
            'sugar' => [['en' => 'Sugar', 'ar' => 'السكر'], [['No sugar', 'بدون سكر', 0, false], ['Less sugar', 'سكر خفيف', 0, false], ['Regular', 'عادي', 0, true]]],
            'milk' => [['en' => 'Milk', 'ar' => 'الحليب'], [['Full fat', 'كامل الدسم', 0, true], ['Oat', 'شوفان', 200, false], ['Almond', 'لوز', 200, false]]],
            'size' => [['en' => 'Size', 'ar' => 'الحجم'], [['Regular', 'عادي', 0, true], ['Large', 'كبير', 300, false]]],
        ];

        $groups = [];

        foreach (array_keys($definitions) as $sort => $key) {
            [$name, $options] = $definitions[$key];
            $group = ModifierGroup::withoutTenancy()->create([
                'restaurant_id' => $this->restaurant->id,
                'name' => $name,
                'kind' => ModifierKind::Choice,
                'min_select' => 1,
                'max_select' => 1,
                'sort' => $sort,
            ]);

            foreach ($options as $i => [$en, $ar, $delta, $default]) {
                ModifierOption::withoutTenancy()->create([
                    'restaurant_id' => $this->restaurant->id,
                    'modifier_group_id' => $group->id,
                    'name' => ['en' => $en, 'ar' => $ar],
                    'price_delta_minor' => $delta,
                    'is_default' => $default,
                    'sort' => $i,
                ]);
            }

            $groups[$key] = $group;
        }

        return $groups;
    }

    private function section(string $icon, string $en, string $ar, ?string $descEn, ?string $descAr): MenuCategory
    {
        return MenuCategory::withoutTenancy()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => ['en' => $en, 'ar' => $ar],
            'description' => $descEn !== null ? ['en' => $descEn, 'ar' => $descAr] : null,
            'icon' => $icon,
            'sort' => (int) MenuCategory::withoutTenancy()->where('restaurant_id', $this->restaurant->id)->max('sort') + 1,
        ]);
    }
}
