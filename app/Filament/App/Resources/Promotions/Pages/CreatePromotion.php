<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Promotions\Pages;

use App\Domain\Promotions\Models\Promotion;
use App\Filament\App\Resources\Promotions\PromotionResource;
use App\Filament\App\Workspace;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $promotion = Promotion::query()->create(['restaurant_id' => Workspace::restaurant()->id, ...PromotionResource::attributes($data)]);
        activity()->performedOn($promotion)->causedBy(Workspace::user())->event('promotion.created')->log('promotion.created');

        return $promotion;
    }

    /** Coupon promotions continue to the page where their codes are made. */
    protected function getRedirectUrl(): string
    {
        return PromotionResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
