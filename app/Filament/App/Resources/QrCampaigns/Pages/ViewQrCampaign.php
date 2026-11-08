<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCampaigns\Pages;

use App\Domain\Branches\QrCodes;
use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\QrFunnel;
use App\Filament\App\Resources\QrCampaigns\QrCampaignResource;
use App\Filament\App\Workspace;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One tracked QR code: its link and code, what it brought (scans → visits
 * → orders → revenue, and the last 30 days), and the files to print.
 */
class ViewQrCampaign extends Page
{
    use InteractsWithRecord;

    protected static string $resource = QrCampaignResource::class;

    protected string $view = 'filament.app.resources.qr-campaigns.view-qr-campaign';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(QrCampaignResource::canView($this->record), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->campaign()->name;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            QrCampaignResource::getUrl() => QrCampaignResource::getPluralModelLabel(),
            '#' => $this->campaign()->name,
        ];
    }

    protected function getHeaderActions(): array
    {
        $downloads = collect(['png', 'svg', 'a5', 'a4', 'sticker'])->map(fn (string $format): Action => Action::make('download_'.$format)
            ->label(__('qr.download.'.$format))
            ->icon(in_array($format, ['png', 'svg'], true) ? 'heroicon-m-photo' : 'heroicon-m-document-arrow-down')
            ->action(fn (): StreamedResponse => $this->download($format)))->all();

        return [
            ActionGroup::make($downloads)->label(__('qr.download.label'))->icon('heroicon-m-arrow-down-tray')->button()->color('primary'),
            Action::make('edit')->label(__('campaigns.admin.actions.edit'))->icon('heroicon-m-pencil-square')->color('gray')
                ->visible(fn (): bool => QrCampaignResource::canEdit($this->campaign()))
                ->url(fn (): string => QrCampaignResource::getUrl('edit', ['record' => $this->campaign()])),
            Action::make('toggle')
                ->label(fn (): string => $this->campaign()->is_active ? __('qr.switch_off') : __('qr.switch_on'))
                ->icon(fn (): string => $this->campaign()->is_active ? 'heroicon-m-pause' : 'heroicon-m-play')
                ->color('gray')
                ->visible(fn (): bool => QrCampaignResource::canEdit($this->campaign()))
                ->action(function (): void {
                    $campaign = $this->campaign();
                    $campaign->forceFill(['is_active' => ! $campaign->is_active])->save();
                    activity()->performedOn($campaign)->causedBy(Workspace::user())->event('qr_campaign.'.($campaign->is_active ? 'switched_on' : 'switched_off'))
                        ->withProperties(['restaurant_id' => $campaign->restaurant_id])->log('qr_campaign.updated');
                    Notification::make()->success()->title($campaign->is_active ? __('qr.switched_on') : __('qr.switched_off'))->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $campaign = $this->campaign()->loadMissing('branch');
        $restaurant = Workspace::restaurant();

        return [
            'campaign' => $campaign,
            'status' => QrCampaignResource::status($campaign),
            'funnel' => QrFunnel::for($campaign, $restaurant->timezone),
            'svg' => app(QrCodes::class)->svg($campaign->url()),
            'currency' => $restaurant->currency,
        ];
    }

    private function download(string $format): StreamedResponse
    {
        $campaign = $this->campaign()->loadMissing('branch');
        $restaurant = Workspace::restaurant();
        $codes = app(QrCodes::class);
        // Names are transliterated (Arabic too); one with nothing to keep falls back to the code.
        $name = $restaurant->slug.'-'.(Str::slug($campaign->name) ?: Str::lower($campaign->code)).'-qr';

        [$body, $extension, $type] = match ($format) {
            'png' => [$codes->png($campaign->url()), 'png', 'image/png'],
            'svg' => [$codes->svg($campaign->url()), 'svg', 'image/svg+xml'],
            'a4' => [$codes->poster($restaurant, $campaign->branch, $campaign->url(), 'order', 'a4'), 'pdf', 'application/pdf'],
            'sticker' => [$codes->poster($restaurant, $campaign->branch, $campaign->url(), 'order', 'sticker'), 'pdf', 'application/pdf'],
            default => [$codes->poster($restaurant, $campaign->branch, $campaign->url(), 'order', 'a5'), 'pdf', 'application/pdf'],
        };

        $suffix = in_array($format, ['a4', 'a5', 'sticker'], true) ? '-'.$format : '';

        return response()->streamDownload(function () use ($body): void {
            echo $body;
        }, $name.$suffix.'.'.$extension, ['Content-Type' => $type]);
    }

    private function campaign(): QrCampaign
    {
        /** @var QrCampaign $campaign */
        $campaign = $this->getRecord();

        return $campaign;
    }
}
