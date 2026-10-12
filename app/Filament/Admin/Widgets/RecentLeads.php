<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Growth\Models\Lead;
use App\Filament\Admin\Resources\Leads\LeadResource;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentLeads extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ManageLeads);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.recent_leads.title'))
            ->query(Lead::query()->open()->latest()->limit(5))
            ->paginated(false)
            ->recordUrl(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('restaurant_name')
                    ->label(__('leads.fields.restaurant_name'))
                    ->description(fn (Lead $record): string => $record->contact_name)
                    ->weight('medium'),
                TextColumn::make('branches_count')->label(__('leads.fields.branches_count'))->alignCenter(),
                TextColumn::make('status')->label(__('clients.fields.status'))->badge(),
                TextColumn::make('created_at')->label(__('leads.fields.received'))->since(),
            ])
            ->emptyStateHeading(__('dashboard.recent_leads.empty'))
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
