<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Domain\Billing\Models\Plan;
use App\Filament\Admin\Resources\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    /**
     * The key is optional here: left empty, it comes from the English name
     * ("Pro Plus" → "pro-plus"), made unique with a number when needed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['key'] ?? null)) {
            $base = Str::slug((string) data_get($data, 'name.en')) ?: 'plan';
            $base = Str::limit($base, 36, '');
            $key = $base;

            for ($n = 2; Plan::query()->where('key', $key)->exists(); $n++) {
                $key = $base.'-'.$n;
            }

            $data['key'] = $key;
        }

        return $data;
    }
}
