<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuThemes\Pages;

use App\Domain\Themes\Models\MenuTheme;
use App\Filament\Admin\Resources\MenuThemes\MenuThemeResource;
use App\Filament\Admin\Resources\MenuThemes\Schemas\MenuThemeForm;
use App\Filament\Admin\Resources\MenuThemes\Tables\MenuThemesTable;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuTheme extends CreateRecord
{
    protected static string $resource = MenuThemeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MenuThemeForm::dehydrate($data) + [
            'is_built_in' => false,
            'is_default' => false,
            'sort' => (int) MenuTheme::query()->max('sort') + 1,
            'description' => ['en' => '', 'ar' => ''],
        ];
    }

    protected function afterCreate(): void
    {
        /** @var MenuTheme $theme */
        $theme = $this->getRecord();
        MenuThemesTable::audit($theme, 'menu_theme.created', 'Theme template created');
    }
}
