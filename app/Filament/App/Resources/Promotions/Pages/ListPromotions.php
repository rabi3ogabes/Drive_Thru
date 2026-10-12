<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Promotions\Pages;

use App\Filament\App\Resources\Promotions\PromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('promotions.admin.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('promotions.admin.add'))->icon('heroicon-m-plus')];
    }
}
