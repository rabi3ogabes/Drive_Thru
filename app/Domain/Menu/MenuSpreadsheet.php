<?php

declare(strict_types=1);

namespace App\Domain\Menu;

use App\Domain\Menu\Enums\Allergen;
use App\Domain\Menu\Enums\ProductTag;
use App\Domain\Menu\Models\MenuCategory;
use App\Domain\Menu\Models\Product;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * The menu as an Excel workbook: export it, edit it anywhere, import it
 * back. Rows are matched by id, then SKU; new rows create products; unknown
 * sections are created. Import is all-or-nothing: any invalid row and
 * nothing changes, with every problem listed by row number.
 */
final class MenuSpreadsheet
{
    public const COLUMNS = [
        'id', 'sku', 'section_en', 'section_ar', 'name_en', 'name_ar', 'description_en', 'description_ar',
        'price', 'discount_percent', 'calories', 'prep_minutes', 'allergens', 'tags',
        'popular', 'new', 'limited', 'available',
    ];

    public function export(Restaurant $restaurant, string $path, bool $templateOnly = false): void
    {
        $options = new Options;
        $options->setColumnWidth(8, 1);
        $options->setColumnWidth(14, 2, 10, 11, 12);
        $options->setColumnWidth(22, 3, 4, 5, 6, 13, 14);
        $options->setColumnWidth(40, 7, 8);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Menu');

        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('0F3B3A');
        $writer->addRow(Row::fromValues(self::COLUMNS, $header));

        $decimals = Money::decimals($restaurant->currency);

        if (! $templateOnly) {
            Product::withoutTenancy()
                ->with('category')
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('menu_category_id')
                ->orderBy('sort')
                ->each(function (Product $product) use ($writer, $decimals): void {
                    $writer->addRow(Row::fromValues([
                        $product->id,
                        $product->sku,
                        $product->category?->getTranslation('name', 'en', false),
                        $product->category?->getTranslation('name', 'ar', false),
                        $product->getTranslation('name', 'en', false),
                        $product->getTranslation('name', 'ar', false),
                        $product->getTranslation('description', 'en', false),
                        $product->getTranslation('description', 'ar', false),
                        round($product->price_minor / 10 ** $decimals, $decimals),
                        $product->discount_percent,
                        $product->calories,
                        $product->prep_minutes,
                        $product->allergens->map->value->join(','),
                        $product->tags->map->value->join(','),
                        $product->is_popular ? 1 : 0,
                        $product->is_new ? 1 : 0,
                        $product->is_limited ? 1 : 0,
                        $product->is_available ? 1 : 0,
                    ]));
                });
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Guide');
        foreach ($this->guide() as $line) {
            $writer->addRow(Row::fromValues($line));
        }

        $writer->close();
    }

    /**
     * Reads and validates a workbook without changing anything.
     *
     * @return array{rows: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    public function parse(Restaurant $restaurant, string $path): array
    {
        $rows = [];
        $errors = [];

        try {
            $reader = new Reader;
            $reader->open($path);
        } catch (Throwable) {
            return ['rows' => [], 'errors' => [['row' => 0, 'message' => __('menu.import.errors.unreadable')]]];
        }

        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $values = array_map(fn (mixed $v): mixed => is_string($v) ? trim($v) : $v, $row->toArray());

                if ($headers === null) {
                    $headers = array_map(fn (mixed $h): string => mb_strtolower(trim(is_scalar($h) ? (string) $h : '')), $values);
                    $missing = array_diff(['name_en', 'name_ar', 'price'], $headers);

                    if ($missing !== []) {
                        $errors[] = ['row' => 1, 'message' => __('menu.import.errors.columns', ['columns' => implode(', ', $missing)])];
                        break 2;
                    }

                    continue;
                }

                if (array_filter($values, fn (mixed $v): bool => $v !== null && $v !== '') === []) {
                    continue;
                }

                $data = [];
                foreach ($headers as $i => $header) {
                    $data[$header] = $values[$i] ?? null;
                }

                [$clean, $rowErrors] = $this->validate($restaurant, $data);

                foreach ($rowErrors as $message) {
                    $errors[] = ['row' => (int) $index, 'message' => $message];
                }

                if ($rowErrors === []) {
                    $rows[] = $clean;
                }
            }

            break; // only the first sheet holds the menu
        }

        $reader->close();

        if ($headers === null) {
            $errors[] = ['row' => 0, 'message' => __('menu.import.errors.empty')];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Applies validated rows in one transaction.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int}
     */
    public function apply(Restaurant $restaurant, array $rows, ?User $actor = null): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($restaurant, $rows, &$created, &$updated): void {
            $sections = [];

            foreach ($rows as $row) {
                $categoryId = null;

                if (filled($row['section_en'])) {
                    $key = mb_strtolower((string) $row['section_en']);
                    $categoryId = $sections[$key] ??= $this->section($restaurant, (string) $row['section_en'], (string) ($row['section_ar'] ?: $row['section_en']));
                }

                $product = $this->find($restaurant, $row['id'], $row['sku']);
                $attributes = [
                    'sku' => $row['sku'],
                    'menu_category_id' => $categoryId,
                    'name' => ['en' => $row['name_en'], 'ar' => $row['name_ar']],
                    'description' => array_filter(['en' => $row['description_en'], 'ar' => $row['description_ar']], 'filled') ?: null,
                    'price_minor' => $row['price_minor'],
                    'discount_percent' => $row['discount_percent'],
                    'calories' => $row['calories'],
                    'prep_minutes' => $row['prep_minutes'],
                    'allergens' => $row['allergens'],
                    'tags' => $row['tags'],
                    'is_popular' => $row['popular'],
                    'is_new' => $row['new'],
                    'is_limited' => $row['limited'],
                    'is_available' => $row['available'],
                ];

                if ($product === null) {
                    Product::withoutTenancy()->create($attributes + ['restaurant_id' => $restaurant->id]);
                    $created++;
                } else {
                    $product->update($attributes);
                    $updated++;
                }
            }
        });

        activity()->performedOn($restaurant)->causedBy($actor)->event('menu.imported')
            ->withProperties(['created' => $created, 'updated' => $updated])
            ->log('menu.imported');

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validate(Restaurant $restaurant, array $data): array
    {
        $errors = [];
        $text = fn (string $key, int $max): ?string => filled($data[$key] ?? null) ? mb_substr((string) $data[$key], 0, $max) : null;

        $nameEn = $text('name_en', 120);
        $nameAr = $text('name_ar', 120);

        if ($nameEn === null || $nameAr === null) {
            $errors[] = __('menu.import.errors.name');
        }

        $price = $data['price'] ?? null;
        if (! is_numeric($price) || (float) $price < 0) {
            $errors[] = __('menu.import.errors.price');
        }

        $discount = $data['discount_percent'] ?? 0;
        if ($discount !== '' && (! is_numeric($discount) || $discount < 0 || $discount > 90)) {
            $errors[] = __('menu.import.errors.discount');
        }

        $list = function (string $key, string $enum) use ($data, &$errors): array {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) ($data[$key] ?? ''))), 'filled'));
            $valid = [];

            foreach ($values as $value) {
                $case = $enum::tryFrom(mb_strtolower($value));
                $case === null
                    ? $errors[] = __('menu.import.errors.unknown', ['value' => $value, 'column' => $key])
                    : $valid[] = $case->value;
            }

            return array_values(array_unique($valid));
        };

        $id = $data['id'] ?? null;
        if (filled($id) && (! is_numeric($id) || ! Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->whereKey((int) $id)->exists())) {
            $errors[] = __('menu.import.errors.id', ['id' => $id]);
        }

        $int = fn (string $key, int $max): ?int => is_numeric($data[$key] ?? null) ? max(0, min($max, (int) $data[$key])) : null;
        $flag = fn (string $key, bool $default): bool => ($data[$key] ?? null) === null || $data[$key] === ''
            ? $default
            : in_array(mb_strtolower((string) $data[$key]), ['1', 'yes', 'true', 'نعم', 'y'], true);

        $clean = [
            'id' => filled($id) && is_numeric($id) ? (int) $id : null,
            'sku' => $text('sku', 40),
            'section_en' => $text('section_en', 80),
            'section_ar' => $text('section_ar', 80),
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'description_en' => $text('description_en', 500),
            'description_ar' => $text('description_ar', 500),
            'price_minor' => is_numeric($price) ? Money::toMinor((string) $price, $restaurant->currency) : 0,
            'discount_percent' => is_numeric($discount) ? (int) $discount : 0,
            'calories' => $int('calories', 5000),
            'prep_minutes' => $int('prep_minutes', 120),
            'allergens' => $list('allergens', Allergen::class),
            'tags' => $list('tags', ProductTag::class),
            'popular' => $flag('popular', false),
            'new' => $flag('new', false),
            'limited' => $flag('limited', false),
            'available' => $flag('available', true),
        ];

        return [$clean, $errors];
    }

    private function find(Restaurant $restaurant, ?int $id, ?string $sku): ?Product
    {
        $query = Product::withoutTenancy()->where('restaurant_id', $restaurant->id);

        if ($id !== null) {
            return (clone $query)->find($id);
        }

        return $sku !== null ? $query->where('sku', $sku)->first() : null;
    }

    private function section(Restaurant $restaurant, string $en, string $ar): int
    {
        $existing = MenuCategory::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->get()
            ->first(fn (MenuCategory $category): bool => mb_strtolower((string) $category->getTranslation('name', 'en', false)) === mb_strtolower($en));

        if ($existing !== null) {
            return $existing->id;
        }

        return MenuCategory::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'name' => ['en' => $en, 'ar' => $ar],
            'sort' => (int) MenuCategory::withoutTenancy()->where('restaurant_id', $restaurant->id)->max('sort') + 1,
        ])->id;
    }

    /**
     * @return list<list<string>>
     */
    private function guide(): array
    {
        return [
            ['How to use this sheet', 'طريقة استخدام هذا الملف'],
            ['Keep the header row. One product per row.', 'أبقِ صف العناوين. منتج واحد في كل صف.'],
            ['id: leave empty for new products; keep it to update.', 'id: اتركه فارغًا للمنتجات الجديدة، وأبقِه للتعديل.'],
            ['price: in your currency, e.g. 25.50', 'price: بعملتك، مثال 25.50'],
            ['allergens: '.implode(', ', array_map(fn (Allergen $a): string => $a->value, Allergen::cases())), ''],
            ['tags: '.implode(', ', array_map(fn (ProductTag $t): string => $t->value, ProductTag::cases())), ''],
            ['popular / new / limited / available: 1 or 0', 'popular / new / limited / available: 1 أو 0'],
        ];
    }
}
