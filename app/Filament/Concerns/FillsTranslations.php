<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * For Edit/View pages of models using spatie/laravel-translatable: fill the
 * form with every translation ({"en": …, "ar": …}) instead of only the
 * current-locale string, so Bilingual inputs ("name.en", "name.ar") work.
 */
trait FillsTranslations
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $attributes = property_exists($record, 'translatable') && is_array($record->translatable) ? $record->translatable : [];

        foreach ($attributes as $attribute) {
            $raw = $record->getRawOriginal($attribute);
            $data[$attribute] = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }

        return $this->afterTranslationsFilled($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function afterTranslationsFilled(array $data): array
    {
        return $data;
    }
}
