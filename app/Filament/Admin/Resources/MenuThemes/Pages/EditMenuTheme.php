<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuThemes\Pages;

use App\Domain\Themes\Models\MenuTheme;
use App\Filament\Admin\Resources\MenuThemes\MenuThemeResource;
use App\Filament\Admin\Resources\MenuThemes\Schemas\MenuThemeForm;
use App\Filament\Admin\Resources\MenuThemes\Tables\MenuThemesTable;
use App\Filament\Concerns\FillsTranslations;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuTheme extends EditRecord
{
    use FillsTranslations;

    protected static string $resource = MenuThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function afterTranslationsFilled(array $data): array
    {
        return MenuThemeForm::hydrate($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var MenuTheme $record */
        $record = $this->getRecord();

        return MenuThemeForm::dehydrate($data, $record);
    }

    protected function afterSave(): void
    {
        /** @var MenuTheme $theme */
        $theme = $this->getRecord();
        MenuThemesTable::audit($theme, 'menu_theme.updated', 'Theme template updated');
    }
}
