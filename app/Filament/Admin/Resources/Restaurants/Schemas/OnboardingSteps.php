<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Restaurants\Schemas;

use App\Domain\Billing\Models\Plan;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Domain\Tenancy\Data\OnboardClientData;
use App\Models\User;
use App\Support\Digits;
use App\Support\Locales;
use App\Support\Money;
use DateTimeZone;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Str;

/**
 * The fields that open a client account, shared by the "New client" wizard
 * and by "Convert lead to client".
 */
final class OnboardingSteps
{
    /**
     * @return array<Step>
     */
    public static function make(): array
    {
        return [
            Step::make(__('clients.wizard.steps.brand'))
                ->description(__('clients.wizard.steps.brand_hint'))
                ->icon('heroicon-o-building-storefront')
                ->schema(self::brandFields()),
            Step::make(__('clients.wizard.steps.owner'))
                ->description(__('clients.wizard.steps.owner_hint'))
                ->icon('heroicon-o-user-circle')
                ->schema(self::ownerFields()),
            Step::make(__('clients.wizard.steps.plan'))
                ->description(__('clients.wizard.steps.plan_hint'))
                ->icon('heroicon-o-credit-card')
                ->schema(self::planFields()),
            Step::make(__('clients.wizard.steps.review'))
                ->description(__('clients.wizard.steps.review_hint'))
                ->icon('heroicon-o-check-badge')
                ->schema([self::review()]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function brandFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('name.en')
                    ->label(__('admin.fields.in_english', ['field' => __('clients.fields.name')]))
                    ->required()
                    ->maxLength(80)
                    ->extraInputAttributes(['dir' => 'ltr', 'lang' => 'en'])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (! $get('slug_edited')) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('name.ar')
                    ->label(__('admin.fields.in_arabic', ['field' => __('clients.fields.name')]))
                    ->required()
                    ->maxLength(80)
                    ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                TextInput::make('slug')
                    ->label(__('clients.fields.slug'))
                    ->helperText(__('clients.fields.slug_hint'))
                    ->prefix(Str::after(url('/'), '://').'/')
                    ->required()
                    ->alphaDash()
                    ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state))
                    ->maxLength(60)
                    ->notIn(CreateRestaurant::reservedSlugs())
                    ->unique(table: 'restaurants', column: 'slug', ignoreRecord: false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set) => $set('slug_edited', true))
                    ->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('contact_phone')
                    ->label(__('clients.fields.contact_phone'))
                    ->tel()
                    ->maxLength(30),
                Select::make('default_locale')
                    ->label(__('clients.fields.default_locale'))
                    ->options(collect(Locales::codes())->mapWithKeys(fn (string $c): array => [$c => Locales::native($c)])->all())
                    ->default('ar')
                    ->required()
                    ->native(false),
                Select::make('timezone')
                    ->label(__('clients.fields.timezone'))
                    ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                    ->default('Asia/Qatar')
                    ->searchable()
                    ->required(),
                Hidden::make('slug_edited')->dehydrated(false),
            ]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function ownerFields(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('owner_name')
                    ->label(__('clients.fields.owner_name'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('owner_email')
                    ->label(__('clients.fields.owner_email'))
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->live(onBlur: true)
                    ->helperText(fn (?string $state): string => filled($state) && User::query()->where('email', Str::lower(trim($state)))->exists()
                        ? __('clients.wizard.existing_owner_hint')
                        : __('clients.wizard.new_owner_hint')),
                TextInput::make('owner_phone')
                    ->label(__('clients.fields.owner_phone'))
                    ->tel()
                    ->maxLength(30),
                Toggle::make('send_invitation')
                    ->label(__('clients.fields.send_invitation'))
                    ->helperText(__('clients.fields.send_invitation_hint'))
                    ->default(true)
                    ->inline(false),
            ]),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function planFields(): array
    {
        return [
            Radio::make('plan_id')
                ->label(__('clients.fields.plan'))
                ->options(fn (): array => Plan::query()->available()->get()->mapWithKeys(fn (Plan $plan): array => [
                    $plan->id => $plan->name.' · '.$plan->formattedPrice().($plan->isCustomPriced() ? '' : ' '.__('plans.per_branch_per', ['interval' => $plan->interval->getLabel()])),
                ])->all())
                ->descriptions(fn (): array => Plan::query()->available()->get()->mapWithKeys(fn (Plan $plan): array => [$plan->id => (string) $plan->description])->all())
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $plan = self::plan($state);
                    $set('trial_days', $plan->trial_days ?? 0);
                    $set('negotiated_price', $plan?->isCustomPriced() ?? false);
                }),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                TextInput::make('branches')
                    ->label(__('clients.fields.branches'))
                    ->helperText(function (Get $get): ?string {
                        $limit = self::plan($get('plan_id'))?->limit('branches');

                        if ($limit === null) {
                            return null;
                        }

                        $text = trans('clients.wizard.branch_limit', ['count' => Digits::number($limit)]);

                        return is_string($text) ? $text : null;
                    })
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (Get $get): ?int => self::plan($get('plan_id'))?->limit('branches'))
                    ->default(1)
                    ->required(),
                TextInput::make('trial_days')
                    ->label(__('plans.fields.trial_days'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(90)
                    ->suffix(__('plans.fields.days'))
                    ->default(14)
                    ->required(),
                Toggle::make('negotiated_price')
                    ->label(__('clients.fields.negotiated_price'))
                    ->live()
                    ->inline(false),
                TextInput::make('unit_price')
                    ->label(__('clients.fields.unit_price'))
                    ->helperText(__('clients.fields.unit_price_hint'))
                    ->visible(fn (Get $get): bool => (bool) $get('negotiated_price'))
                    ->required(fn (Get $get): bool => (bool) $get('negotiated_price'))
                    ->inputMode('decimal')
                    ->rule('regex:/^[0-9٠-٩.,٫ ]+$/u')
                    ->suffix(fn (Get $get): string => Money::symbol(self::plan($get('plan_id'))->currency ?? 'QAR'))
                    ->columnSpan(2),
            ]),
        ];
    }

    private static function review(): Section
    {
        return Section::make()->schema([
            Text::make(fn (Get $get): string => __('clients.wizard.review', [
                'name' => (string) $get('name.en'),
                'owner' => (string) $get('owner_name'),
                'email' => (string) $get('owner_email'),
                'plan' => (string) self::plan($get('plan_id'))?->name,
                'branches' => (int) $get('branches'),
            ])),
            Text::make(fn (Get $get): string => (int) $get('trial_days') > 0
                ? trans_choice('clients.wizard.review_trial', (int) $get('trial_days'), ['count' => (int) $get('trial_days')])
                : __('clients.wizard.review_no_trial'))
                ->color('gray'),
            Text::make(fn (Get $get): string => $get('send_invitation')
                ? __('clients.wizard.review_invite')
                : __('clients.wizard.review_no_invite'))
                ->color('gray'),
        ]);
    }

    public static function plan(mixed $id): ?Plan
    {
        return is_numeric($id) ? Plan::query()->find((int) $id) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function toData(array $data): OnboardClientData
    {
        $plan = Plan::query()->findOrFail((int) $data['plan_id']);

        return new OnboardClientData(
            name: ['en' => trim((string) data_get($data, 'name.en')), 'ar' => trim((string) data_get($data, 'name.ar'))],
            ownerName: (string) $data['owner_name'],
            ownerEmail: (string) $data['owner_email'],
            plan: $plan,
            ownerPhone: $data['owner_phone'] ?? null,
            slug: isset($data['slug']) ? Str::slug((string) $data['slug']) : null,
            branches: max(1, (int) ($data['branches'] ?? 1)),
            trialDays: isset($data['trial_days']) ? (int) $data['trial_days'] : null,
            unitPriceMinor: ! empty($data['negotiated_price']) && filled($data['unit_price'] ?? null)
                ? Money::toMinor((string) $data['unit_price'], $plan->currency)
                : null,
            timezone: (string) ($data['timezone'] ?? 'Asia/Qatar'),
            locale: (string) ($data['default_locale'] ?? 'ar'),
            contactPhone: $data['contact_phone'] ?? null,
            sendInvitation: (bool) ($data['send_invitation'] ?? true),
        );
    }
}
