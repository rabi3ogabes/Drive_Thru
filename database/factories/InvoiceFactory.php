<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'number' => 'DTP-TEST-'.Str::upper(Str::random(8)),
            'status' => InvoiceStatus::Open,
            'currency' => 'QAR',
            'subtotal_minor' => 64900,
            'total_minor' => 64900,
            'issued_at' => now(),
            'due_at' => now()->addWeek(),
        ];
    }
}
