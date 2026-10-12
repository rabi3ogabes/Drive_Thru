<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Pages;

use App\Filament\Admin\Resources\Restaurants\RestaurantResource;
use App\Filament\Admin\Resources\Restaurants\Schemas\RestaurantForm;
use App\Filament\Concerns\FillsTranslations;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRestaurant extends EditRecord
{
    use FillsTranslations;

    protected static string $resource = RestaurantResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function afterTranslationsFilled(array $data): array
    {
        $data['feature_overrides'] = RestaurantForm::overridesToForm($this->getRecord()->getAttribute('feature_overrides'));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['feature_overrides'] = RestaurantForm::overridesFromForm((array) ($data['feature_overrides'] ?? []));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return RestaurantResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
