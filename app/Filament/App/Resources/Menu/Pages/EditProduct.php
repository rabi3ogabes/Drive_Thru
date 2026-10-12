<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menu\Pages;

use App\Domain\Menu\Models\Product;
use App\Filament\App\Actions\MenuActions;
use App\Filament\App\Resources\Menu\Pages\Concerns\SyncsProductMedia;
use App\Filament\App\Resources\Menu\ProductResource;
use App\Filament\Concerns\FillsTranslations;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use FillsTranslations, SyncsProductMedia;

    protected static string $resource = ProductResource::class;

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $this->originalImagePath = $record instanceof Product ? $record->getOriginal('image_path') : null;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Product) {
            $this->afterProductSaved($record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            MenuActions::duplicate(),
            DeleteAction::make()->modalDescription(__('menu.products.delete_description')),
        ];
    }
}
