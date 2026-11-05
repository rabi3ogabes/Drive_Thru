<?php

declare(strict_types=1);

namespace App\Domain\Themes\Models;

use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A font a restaurant uploaded for its theme (WOFF2). Its CSS family is
 * "brand-{id}", so the name the owner typed never reaches the stylesheet.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property string $name
 * @property string $scripts latin, arabic or both
 * @property string $path
 * @property int $size_bytes
 */
#[Fillable(['restaurant_id', 'name', 'scripts', 'path', 'size_bytes'])]
class BrandFont extends Model
{
    use BelongsToRestaurant;

    public const SCRIPTS = ['latin', 'arabic', 'both'];

    public const LIMIT = 4;

    public const MAX_BYTES = 1048576;

    protected $attributes = [
        'name' => '',
        'scripts' => 'both',
        'path' => '',
        'size_bytes' => 0,
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function key(): string
    {
        return "brand:{$this->id}";
    }

    public function family(): string
    {
        return "brand-{$this->id}";
    }
}
