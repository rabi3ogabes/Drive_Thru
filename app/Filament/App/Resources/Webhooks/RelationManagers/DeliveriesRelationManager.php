<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Webhooks\RelationManagers;

use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Webhooks\DispatchWebhook;
use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Filament\App\Workspace;
use App\Support\Lang;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** The delivery log of one endpoint: what was sent, the receiver's answer, and replay. */
class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('integrations.webhooks.deliveries.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('event')
                    ->label(__('integrations.webhooks.deliveries.event'))
                    ->formatStateUsing(fn (string $state): string => WebhookEvent::tryFrom($state)?->getLabel() ?? __('integrations.webhooks.deliveries.ping'))
                    ->description(fn (WebhookDelivery $record): string => $record->event_id)
                    ->weight('medium'),
                TextColumn::make('status')
                    ->label(__('integrations.webhooks.fields.status'))
                    ->formatStateUsing(fn (string $state): string => __('integrations.webhooks.deliveries.status.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WebhookDelivery::DELIVERED => 'success',
                        WebhookDelivery::FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('response_status')
                    ->label(__('integrations.webhooks.deliveries.response'))
                    ->placeholder('—')
                    ->description(fn (WebhookDelivery $record): ?string => $record->status === WebhookDelivery::PENDING && $record->next_attempt_at !== null
                        ? Lang::text('integrations.webhooks.deliveries.retry_at', ['time' => $record->next_attempt_at->diffForHumans()])
                        : null),
                TextColumn::make('attempts')->label(__('integrations.webhooks.deliveries.attempts'))->numeric(),
                TextColumn::make('created_at')->label(__('integrations.webhooks.deliveries.sent'))->since()->dateTimeTooltip(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('integrations.webhooks.fields.status'))->options([
                    WebhookDelivery::DELIVERED => __('integrations.webhooks.deliveries.status.delivered'),
                    WebhookDelivery::PENDING => __('integrations.webhooks.deliveries.status.pending'),
                    WebhookDelivery::FAILED => __('integrations.webhooks.deliveries.status.failed'),
                ]),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label(__('integrations.webhooks.deliveries.inspect'))
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(fn (WebhookDelivery $record): string => $record->event)
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalContent(fn (WebhookDelivery $record): HtmlString => new HtmlString(view('filament.app.webhook-delivery', ['delivery' => $record])->render())),
                Action::make('replay')
                    ->label(__('integrations.webhooks.deliveries.replay'))
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription(__('integrations.webhooks.deliveries.replay_description'))
                    ->visible(fn (WebhookDelivery $record): bool => $record->status !== WebhookDelivery::PENDING)
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->action(function (WebhookDelivery $record): void {
                        app(DispatchWebhook::class)->replay($record);
                        activity()->performedOn($this->getOwnerRecord())->causedBy(Workspace::user())->event('webhook.replayed')->withProperties(['event_id' => $record->event_id])->log('webhook.replayed');
                        Notification::make()->success()->title(__('integrations.webhooks.notifications.replayed'))->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading(__('integrations.webhooks.deliveries.empty'));
    }
}
