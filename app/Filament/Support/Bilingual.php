<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;

/**
 * An English + Arabic pair of inputs for one translatable attribute, bound
 * to "attribute.en" and "attribute.ar". The Arabic input is right-to-left.
 */
final class Bilingual
{
    public static function text(string $attribute, string $label, bool $required = true, int $maxLength = 120): Grid
    {
        return Grid::make(['default' => 1, 'md' => 2])->schema([
            TextInput::make("{$attribute}.en")
                ->label(__('admin.fields.in_english', ['field' => $label]))
                ->required($required)
                ->maxLength($maxLength)
                ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            TextInput::make("{$attribute}.ar")
                ->label(__('admin.fields.in_arabic', ['field' => $label]))
                ->required($required)
                ->maxLength($maxLength)
                ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
        ]);
    }

    /**
     * Relationship repeaters fill rows with the current-language string of a
     * translatable column. This swaps in every language so both inputs show.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>  $attributes
     * @return Closure(array<string, mixed>): array<string, mixed>
     */
    public static function fillRelated(string $model, array $attributes): Closure
    {
        return function (array $data) use ($model, $attributes): array {
            $record = isset($data['id']) ? $model::query()->withoutGlobalScopes()->find($data['id']) : null;

            if ($record === null || ! method_exists($record, 'getTranslations')) {
                return $data;
            }

            foreach ($attributes as $attribute) {
                $data[$attribute] = $record->getTranslations($attribute);
            }

            return $data;
        };
    }

    public static function textarea(string $attribute, string $label, bool $required = false, int $maxLength = 1000): Grid
    {
        return Grid::make(['default' => 1, 'md' => 2])->schema([
            Textarea::make("{$attribute}.en")
                ->label(__('admin.fields.in_english', ['field' => $label]))
                ->required($required)
                ->maxLength($maxLength)
                ->rows(3)
                ->autosize()
                ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en']),
            Textarea::make("{$attribute}.ar")
                ->label(__('admin.fields.in_arabic', ['field' => $label]))
                ->required($required)
                ->maxLength($maxLength)
                ->rows(3)
                ->autosize()
                ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
        ]);
    }
}
