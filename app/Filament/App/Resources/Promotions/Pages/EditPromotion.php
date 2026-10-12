<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Promotions\Pages;

use App\Domain\Promotions\Models\Promotion;
use App\Filament\App\Resources\Promotions\PromotionResource;
use App\Filament\App\Workspace;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Promotion $promotion */
        $promotion = $this->getRecord();

        return PromotionResource::formData($promotion);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update(PromotionResource::attributes($data));
        activity()->performedOn($record)->causedBy(Workspace::user())->event('promotion.updated')->log('promotion.updated');

        return $record;
    }
}
