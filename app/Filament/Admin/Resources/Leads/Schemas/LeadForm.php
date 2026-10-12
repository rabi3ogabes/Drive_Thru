<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Schemas;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Billing\Models\Plan;
use App\Domain\Growth\Enums\LeadSource;
use App\Models\User;
use App\Support\Locales;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('leads.sections.request'))
                ->columnSpan(['default' => 3, 'lg' => 2])
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('restaurant_name')->label(__('leads.fields.restaurant_name'))->required()->maxLength(120),
                    TextInput::make('contact_name')->label(__('leads.fields.contact_name'))->required()->maxLength(120),
                    TextInput::make('email')->label(__('leads.fields.email'))->email()->required()->maxLength(190),
                    TextInput::make('phone')->label(__('leads.fields.phone'))->tel()->maxLength(30),
                    TextInput::make('branches_count')->label(__('leads.fields.branches_count'))->numeric()->minValue(1)->maxValue(500)->default(1)->required(),
                    Select::make('plan_id')
                        ->label(__('leads.fields.plan'))
                        ->options(fn (): array => Plan::query()->available()->get()->mapWithKeys(fn (Plan $p): array => [$p->id => $p->name])->all())
                        ->native(false),
                    Textarea::make('message')->label(__('leads.fields.message'))->rows(4)->autosize()->maxLength(2000)->columnSpanFull(),
                ]),
            Section::make(__('leads.sections.pipeline'))
                ->columnSpan(['default' => 3, 'lg' => 1])
                ->schema([
                    Select::make('source')->label(__('leads.fields.source'))->options(LeadSource::class)->default(LeadSource::Manual)->required()->native(false),
                    Select::make('locale')
                        ->label(__('leads.fields.locale'))
                        ->options(collect(Locales::codes())->mapWithKeys(fn (string $c): array => [$c => Locales::native($c)])->all())
                        ->default('ar')
                        ->required()
                        ->native(false),
                    Select::make('assigned_to')
                        ->label(__('leads.fields.assignee'))
                        ->options(fn (): array => self::salesTeam())
                        ->searchable()
                        ->native(false),
                    DateTimePicker::make('follow_up_at')->label(__('leads.fields.follow_up_at'))->native(false)->seconds(false),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function salesTeam(): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('is_super_admin', true)->orWhereNotNull('platform_role_id'))
            ->with('platformRole')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->hasPlatformPermission(PlatformPermission::ManageLeads))
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }
}
