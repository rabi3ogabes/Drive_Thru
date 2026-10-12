<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Schemas;

use App\Domain\Billing\Enums\BillingInterval;
use App\Domain\Billing\Enums\PlanFeature;
use App\Filament\Support\Bilingual;
use App\Support\Money;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('plans.sections.identity'))
                    ->description(__('plans.sections.identity_hint'))
                    ->schema([
                        Bilingual::text('name', __('plans.fields.name'), maxLength: 60),
                        Bilingual::textarea('description', __('plans.fields.description'), maxLength: 300),
                        TextInput::make('key')
                            ->label(__('plans.fields.key'))
                            ->helperText(__('plans.fields.key_hint'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(40)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule)
                            ->disabledOn('edit'),
                    ]),

                Section::make(__('plans.sections.price'))
                    ->description(__('plans.sections.price_hint'))
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('price_minor')
                            ->label(__('plans.fields.price'))
                            ->helperText(__('plans.fields.price_hint'))
                            ->inputMode('decimal')
                            ->suffix(fn (Get $get): string => Money::symbol((string) ($get('currency') ?: 'QAR')))
                            ->formatStateUsing(fn (?int $state, Get $get): ?string => $state === null ? null : Money::toMajor($state, (string) ($get('currency') ?: 'QAR')))
                            ->dehydrateStateUsing(fn (?string $state, Get $get): ?int => blank($state) ? null : Money::toMinor($state, (string) ($get('currency') ?: 'QAR')))
                            ->rule('regex:/^[0-9٠-٩.,٫ ]*$/u'),
                        Select::make('currency')
                            ->label(__('plans.fields.currency'))
                            ->options(array_combine(Money::currencies(), Money::currencies()))
                            ->default('QAR')
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('interval')
                            ->label(__('plans.fields.interval'))
                            ->options(BillingInterval::class)
                            ->default(BillingInterval::Monthly)
                            ->required()
                            ->native(false),
                        TextInput::make('trial_days')
                            ->label(__('plans.fields.trial_days'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->default(14)
                            ->required()
                            ->suffix(__('plans.fields.days')),
                        TextInput::make('limits.branches')
                            ->label(__('plans.fields.max_branches'))
                            ->helperText(__('plans.fields.unlimited_hint'))
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('limits.employees')
                            ->label(__('plans.fields.max_employees'))
                            ->helperText(__('plans.fields.unlimited_hint'))
                            ->numeric()
                            ->minValue(1),
                    ]),

                Section::make(__('plans.sections.features'))
                    ->description(__('plans.sections.features_hint'))
                    ->schema([
                        CheckboxList::make('features')
                            ->hiddenLabel()
                            ->options(PlanFeature::class)
                            ->descriptions(collect(PlanFeature::cases())->mapWithKeys(fn (PlanFeature $f): array => [$f->value => $f->getDescription()])->all())
                            ->columns(['default' => 1, 'md' => 2])
                            ->bulkToggleable()
                            ->gridDirection('row'),
                    ]),
            ]),

            Section::make(__('plans.sections.visibility'))
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    Toggle::make('is_active')->label(__('plans.fields.is_active'))->helperText(__('plans.fields.is_active_hint'))->default(true),
                    Toggle::make('is_public')->label(__('plans.fields.is_public'))->helperText(__('plans.fields.is_public_hint'))->default(true),
                    Toggle::make('is_highlighted')->label(__('plans.fields.is_highlighted'))->helperText(__('plans.fields.is_highlighted_hint')),
                    TextInput::make('sort')->label(__('plans.fields.sort'))->numeric()->default(0)->minValue(0),
                ]),
        ]);
    }
}
