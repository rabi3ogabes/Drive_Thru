<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Leads\Pages;

use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Filament\Admin\Resources\Leads\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('leads.actions.new'))->icon('heroicon-m-plus'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Lead::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $open = collect(LeadStatus::open())->sum(fn (LeadStatus $s): int => (int) ($counts[$s->value] ?? 0));

        $tabs = [
            'open' => Tab::make(__('leads.tabs.open'))
                ->badge((string) $open)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', LeadStatus::open())),
        ];

        foreach (LeadStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->badge((string) ($counts[$status->value] ?? 0))
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        $tabs['all'] = Tab::make(__('leads.tabs.all'));

        return $tabs;
    }
}
