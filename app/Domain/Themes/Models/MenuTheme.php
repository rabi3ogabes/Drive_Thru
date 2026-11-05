<?php

declare(strict_types=1);

namespace App\Domain\Themes\Models;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\ThemeSpec;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * A theme template in the platform library. The super admin curates them;
 * restaurants pick one and store their own changes on top.
 *
 * @property int $id
 * @property string $key
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property array<string, mixed> $spec
 * @property ?string $cover_path
 * @property bool $is_built_in
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort
 */
#[Fillable(['key', 'name', 'description', 'spec', 'cover_path', 'is_built_in', 'is_active', 'is_default', 'sort'])]
class MenuTheme extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    protected $attributes = [
        'name' => '{}',
        'description' => '{}',
        'spec' => '{}',
        'cover_path' => null,
        'is_built_in' => false,
        'is_active' => true,
        'is_default' => false,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'spec' => 'array',
            'is_built_in' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function spec(): ThemeSpec
    {
        return ThemeSpec::fromArray($this->spec + ['cover' => $this->cover_path]);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort');
    }

    public static function default(): self
    {
        return self::query()->where('is_default', true)->first()
            ?? self::query()->orderBy('sort')->firstOrFail();
    }

    /** Restaurants using it, including those still on the default without a saved theme. */
    public function usedByCount(): int
    {
        $query = Restaurant::query()->where('menu_theme->template', $this->key);

        if ($this->is_default) {
            $query->orWhereNull('menu_theme');
        }

        return $query->count();
    }
}
