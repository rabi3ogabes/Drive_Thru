<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages;

use App\Domain\Menu\Models\Product;
use App\Filament\App\Resources\Menu\Pages\Concerns\SyncsProductMedia;
use App\Filament\App\Resources\Menu\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use SyncsProductMedia;

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sort'] = (int) Product::query()->where('menu_category_id', $data['menu_category_id'] ?? null)->max('sort') + 1;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Product) {
            $this->afterProductSaved($record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
