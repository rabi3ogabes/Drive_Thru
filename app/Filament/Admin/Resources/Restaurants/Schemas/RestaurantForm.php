<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Schemas;

use App\Domain\Billing\Enums\PlanFeature;
use App\Filament\Support\Bilingual;
use App\Support\Locales;
use DateTimeZone;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('clients.sections.identity'))
                    ->schema([
                        Bilingual::text('name', __('clients.fields.name'), maxLength: 80),
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('slug')
                                ->label(__('clients.fields.slug'))
                                ->prefix(url('/').'/')
                                ->disabled()
                                ->dehydrated(false),
                            ColorPicker::make('accent_color')
                                ->label(__('clients.fields.accent_color'))
                                ->helperText(__('clients.fields.accent_color_hint')),
                            Select::make('default_locale')
                                ->label(__('clients.fields.default_locale'))
                                ->options(collect(Locales::codes())->mapWithKeys(fn (string $c): array => [$c => Locales::native($c)])->all())
                                ->required()
                                ->native(false),
                            Select::make('timezone')
                                ->label(__('clients.fields.timezone'))
                                ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                                ->searchable()
                                ->required(),
                        ]),
                    ]),

                Section::make(__('clients.sections.billing_details'))
                    ->description(__('clients.sections.billing_details_hint'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('legal_name')->label(__('clients.fields.legal_name'))->maxLength(160),
                        TextInput::make('tax_number')->label(__('clients.fields.tax_number'))->maxLength(40),
                        TextInput::make('contact_email')->label(__('clients.fields.contact_email'))->email()->maxLength(190),
                        TextInput::make('contact_phone')->label(__('clients.fields.contact_phone'))->tel()->maxLength(30),
                        Textarea::make('billing_address')->label(__('clients.fields.billing_address'))->rows(2)->autosize()->columnSpanFull()->maxLength(500),
                    ]),

                Section::make(__('clients.sections.features'))
                    ->description(__('clients.sections.features_hint'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->collapsible()
                    ->schema(collect(PlanFeature::cases())->map(fn (PlanFeature $feature) => ToggleButtons::make("feature_overrides.{$feature->value}")
                        ->label($feature->getLabel())
                        ->helperText($feature->getDescription())
                        ->options([
                            'default' => __('clients.features.default'),
                            'on' => __('clients.features.on'),
                            'off' => __('clients.features.off'),
                        ])
                        ->colors(['default' => 'gray', 'on' => 'success', 'off' => 'danger'])
                        ->default('default')
                        ->inline()
                        ->grouped())->all()),
            ]),

            Section::make(__('clients.sections.internal'))
                ->description(__('clients.sections.internal_hint'))
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    Textarea::make('internal_notes')->hiddenLabel()->rows(8)->autosize()->maxLength(5000),
                ]),
        ]);
    }

    /**
     * {"loyalty": true} → {"loyalty": "on", "ai_assistant": "default", …}
     *
     * @param  ?array<string, bool>  $overrides
     * @return array<string, string>
     */
    public static function overridesToForm(?array $overrides): array
    {
        return collect(PlanFeature::cases())->mapWithKeys(fn (PlanFeature $f): array => [
            $f->value => match ($overrides[$f->value] ?? null) {
                true => 'on',
                false => 'off',
                default => 'default',
            },
        ])->all();
    }

    /**
     * {"loyalty": "on", "promotions": "default"} → {"loyalty": true}
     *
     * @param  array<string, mixed>  $form
     * @return ?array<string, bool>
     */
    public static function overridesFromForm(array $form): ?array
    {
        $overrides = collect($form)
            ->filter(fn (mixed $v): bool => in_array($v, ['on', 'off'], true))
            ->map(fn (string $v): bool => $v === 'on')
            ->all();

        return $overrides === [] ? null : $overrides;
    }
}
