<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads;

use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Filament\Admin\AdminNavigationGroup;
use App\Filament\Admin\Resources\Leads\Pages\CreateLead;
use App\Filament\Admin\Resources\Leads\Pages\EditLead;
use App\Filament\Admin\Resources\Leads\Pages\ListLeads;
use App\Filament\Admin\Resources\Leads\Pages\ViewLead;
use App\Filament\Admin\Resources\Leads\Schemas\LeadForm;
use App\Filament\Admin\Resources\Leads\Schemas\LeadInfolist;
use App\Filament\Admin\Resources\Leads\Tables\LeadsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Clients;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'restaurant_name';

    public static function getModelLabel(): string
    {
        return __('leads.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('leads.plural');
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['restaurant_name', 'contact_name', 'email', 'phone'];
    }

    public static function getNavigationBadge(): ?string
    {
        $new = Lead::query()->where('status', LeadStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return LeadStatus::New->getLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => CreateLead::route('/create'),
            'view' => ViewLead::route('/{record}'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }
}
