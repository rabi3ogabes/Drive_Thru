<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Campaigns\Pages;

use App\Domain\Campaigns\Actions\RunCampaign;
use App\Domain\Campaigns\Actions\SendTestMessage;
use App\Domain\Campaigns\Audience;
use App\Domain\Campaigns\CampaignResults;
use App\Domain\Campaigns\CampaignText;
use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Exceptions\CampaignProblem;
use App\Domain\Campaigns\Jobs\SendCampaign;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Crm\CrmSettings;
use App\Domain\Crm\UnsubscribeLink;
use App\Filament\App\Resources\Campaigns\CampaignResource;
use App\Filament\App\Workspace;
use App\Support\Bidi;
use App\Support\Digits;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One campaign: what it says, who it is for, and how it is doing. From here
 * the owner sends a test to their own phone, then sends or schedules it,
 * and can stop it while it is sending. Results refresh while it sends.
 */
class ViewCampaign extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CampaignResource::class;

    protected string $view = 'filament.app.resources.campaigns.view-campaign';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(CampaignResource::canView($this->record), 403);
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
            CampaignResource::getUrl() => CampaignResource::getPluralModelLabel(),
            '#' => $this->campaign()->name,
        ];
    }

    protected function getHeaderActions(): array
    {
        $campaign = $this->campaign();
        $later = CampaignResource::isForLater($campaign);

        return [
            Action::make('send')
                ->label($later ? __('campaigns.admin.actions.schedule') : __('campaigns.admin.actions.send'))
                ->icon($later ? 'heroicon-m-calendar-days' : 'heroicon-m-paper-airplane')
                ->visible(fn (): bool => $this->campaign()->status === CampaignStatus::Draft)
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-megaphone')
                ->modalHeading(__('campaigns.admin.actions.schedule_heading'))
                ->modalDescription(function (): string {
                    $count = $this->audienceCount();

                    return trans_choice('campaigns.admin.actions.schedule_body', $count, ['count' => Digits::number($count)]);
                })
                ->modalSubmitActionLabel($later ? __('campaigns.admin.actions.schedule') : __('campaigns.admin.actions.send'))
                ->action(fn () => $this->send()),
            Action::make('test')
                ->label(__('campaigns.admin.actions.test'))
                ->icon('heroicon-m-device-phone-mobile')
                ->color('gray')
                ->visible(fn (): bool => $this->campaign()->channel !== CampaignChannel::Push && $this->campaign()->status->isEditable())
                ->modalDescription(__('campaigns.admin.actions.test_hint'))
                ->modalWidth('md')
                ->schema([
                    TextInput::make('phone')->label(__('campaigns.admin.fields.phone'))->tel()->required()->maxLength(20)
                        ->default(fn (): ?string => Workspace::user()->phone ?? Workspace::restaurant()->contact_phone)
                        ->extraInputAttributes(['dir' => 'ltr']),
                    ToggleButtons::make('locale')->label(__('campaigns.admin.fields.locale'))
                        ->options(['ar' => 'العربية', 'en' => 'English'])->default(app()->getLocale() === 'en' ? 'en' : 'ar')->inline()->required(),
                ])
                ->modalSubmitActionLabel(__('campaigns.admin.actions.test'))
                ->action(function (array $data, Action $action): void {
                    try {
                        $phone = app(SendTestMessage::class)->handle($this->campaign(), (string) $data['phone'], (string) $data['locale'], Workspace::user());
                    } catch (CampaignProblem $problem) {
                        Notification::make()->danger()->title($problem->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()->success()->title(__('campaigns.admin.actions.test_sent', ['phone' => Bidi::ltr($phone)]))->send();
                }),
            Action::make('edit')
                ->label(__('campaigns.admin.actions.edit'))
                ->icon('heroicon-m-pencil-square')
                ->color('gray')
                ->visible(fn (): bool => CampaignResource::canEdit($this->campaign()))
                ->url(fn (): string => CampaignResource::getUrl('edit', ['record' => $this->campaign()])),
            ActionGroup::make([
                Action::make('duplicate')
                    ->label(__('campaigns.admin.actions.duplicate'))
                    ->icon('heroicon-m-document-duplicate')
                    ->visible(fn (): bool => CampaignResource::canCreate())
                    ->action(fn () => $this->duplicate()),
                Action::make('cancel')
                    ->label(fn (): string => __('campaigns.admin.actions.cancel'))
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => $this->campaign()->status->isCancellable())
                    ->requiresConfirmation()
                    ->modalHeading(__('campaigns.admin.actions.cancel_heading'))
                    ->modalDescription(__('campaigns.admin.actions.cancel_body'))
                    ->action(function (): void {
                        try {
                            app(RunCampaign::class)->cancel($this->campaign(), Workspace::user());
                        } catch (CampaignProblem $problem) {
                            Notification::make()->danger()->title($problem->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title(__('campaigns.admin.actions.cancelled'))->send();
                    }),
            ])->icon('heroicon-m-ellipsis-horizontal')->color('gray')->button()->label(__('crm.guests.actions.more')),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $campaign = $this->campaign();
        $restaurant = Workspace::restaurant();
        $stop = UnsubscribeLink::sampleUrl($restaurant);
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $text = CampaignText::make($campaign, $restaurant, $locale, $stop);
        $quiet = $campaign->next_attempt_at !== null && CrmSettings::for($restaurant)->quietUntil($restaurant, now()->toImmutable()) !== null;

        return [
            'campaign' => $campaign,
            'results' => CampaignResults::for($campaign),
            'text' => $campaign->channel === CampaignChannel::Push ? $text->offer : $text->sms(),
            'locale' => $locale,
            'audienceCount' => $campaign->status === CampaignStatus::Draft || $campaign->status === CampaignStatus::Scheduled ? $this->audienceCount() : $campaign->recipients_count,
            'tz' => $restaurant->timezone,
            'currency' => $restaurant->currency,
            'hold' => $campaign->status === CampaignStatus::Sending && $campaign->next_attempt_at?->isFuture() ? ($quiet ? 'waiting_quiet' : 'waiting_limit') : null,
        ];
    }

    private function send(): void
    {
        $campaign = $this->campaign();

        if ($this->audienceCount() === 0) {
            Notification::make()->warning()->title(__('campaigns.errors.no_audience'))->send();

            return;
        }

        $run = app(RunCampaign::class);
        $later = CampaignResource::isForLater($campaign);

        try {
            $run->schedule($campaign, $later ? $campaign->scheduled_at : null, Workspace::user());
        } catch (CampaignProblem $problem) {
            Notification::make()->danger()->title($problem->getMessage())->send();

            return;
        }

        if (! $later) {
            $run->start($campaign->refresh());
            SendCampaign::dispatch($campaign->id);
        }

        Notification::make()->success()->title($later ? __('campaigns.admin.actions.scheduled') : __('campaigns.admin.actions.started'))->send();
    }

    private function duplicate(): void
    {
        $source = $this->campaign();
        $copy = Campaign::query()->create([
            'restaurant_id' => $source->restaurant_id,
            'created_by' => Workspace::user()->id,
            'name' => mb_substr(__('campaigns.admin.actions.copy_name', ['name' => $source->name]), 0, 80),
            'channel' => $source->channel,
            'message' => $source->getTranslations('message'),
            'segments' => $source->segments,
            'reward_id' => $source->reward_id,
        ]);

        activity()->performedOn($copy)->causedBy(Workspace::user())->event('campaign.created')
            ->withProperties(['restaurant_id' => $copy->restaurant_id, 'copy_of' => $source->id])->log('campaign.created');

        Notification::make()->success()->title(__('campaigns.admin.actions.duplicated'))->send();
        $this->redirect(CampaignResource::getUrl('edit', ['record' => $copy]));
    }

    private function audienceCount(): int
    {
        $campaign = $this->campaign();

        return app(Audience::class)->count($campaign->restaurant_id, $campaign->segmentList());
    }

    private function campaign(): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->getRecord();

        return $campaign;
    }
}
