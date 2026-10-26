<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $amount_minor
 * @property int $sort
 * @property bool $is_discount subtracted from the subtotal
 */
#[Fillable(['invoice_id', 'description', 'quantity', 'unit_price_minor', 'amount_minor', 'sort', 'is_discount'])]
#[WithoutTimestamps]
class InvoiceLine extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['description'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'quantity' => 1,
        'sort' => 0,
        'is_discount' => false,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'amount_minor' => 'integer',
            'is_discount' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutTenancy();
    }
}
