<?php

declare(strict_types=1);

namespace App\Domain\Customers\Models;

use App\Domain\Customers\Enums\VehicleType;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A car the guest drives in with, so the window team spots them instantly.
 *
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property ?string $nickname
 * @property string $plate
 * @property ?string $make
 * @property ?string $model
 * @property ?string $color
 * @property VehicleType $type
 * @property bool $is_default
 */
#[Fillable(['restaurant_id', 'customer_id', 'nickname', 'plate', 'make', 'model', 'color', 'type', 'is_default'])]
class CustomerVehicle extends Model
{
    use BelongsToRestaurant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'nickname' => null,
        'make' => null,
        'model' => null,
        'color' => null,
        'type' => 'car',
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** "White Land Cruiser · 123456" */
    public function describe(): string
    {
        $car = trim(implode(' ', array_filter([$this->color, $this->make, $this->model])));

        return trim(($this->nickname ?: $car).' · '.$this->plate, ' ·');
    }

    /**
     * What the order keeps, even if the vehicle is edited later.
     *
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'plate' => $this->plate,
            'make' => $this->make,
            'model' => $this->model,
            'color' => $this->color,
            'type' => $this->type->value,
            'label' => $this->describe(),
        ];
    }
}
