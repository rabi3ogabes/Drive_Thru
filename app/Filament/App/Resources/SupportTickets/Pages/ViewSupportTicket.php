<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\SupportTickets\Pages;

use App\Domain\Access\Enums\RestaurantPermission;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Support\Actions\RefundTicket;
use App\Domain\Support\Actions\ResolveTicket;
use App\Domain\Support\Actions\RewardTicket;
use App\Domain\Support\Actions\StaffReply;
use App\Domain\Support\Exceptions\TicketRefused;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\TicketMessage;
use App\Filament\App\Resources\SupportTickets\SupportTicketResource;
use App\Filament\App\Workspace;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One ticket: the order, the guest, the conversation and a reply box, with
 * the ways to put it right (refund, reward) and to close it.
 */
class ViewSupportTicket extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SupportTicketResource::class;

    protected string $view = 'filament.app.resources.support-tickets.view-support-ticket';

    public string $reply = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(SupportTicketResource::canView($this->record), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return __('support.admin.ticket_title', ['number' => $this->ticket()->displayNumber()]);
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            SupportTicketResource::getUrl() => SupportTicketResource::getPluralModelLabel(),
            '#' => $this->ticket()->displayNumber(),
        ];
    }

    public function sendReply(): void
    {
        $this->validate(['reply' => ['required', 'string', 'max:'.TicketMessage::LENGTH]]);

        $this->attempt(function (): void {
            app(StaffReply::class)->handle($this->ticket(), Workspace::user(), $this->reply);
            $this->reply = '';
            Notification::make()->success()->title(__('support.admin.replied'))->send();
        });
    }

    protected function getHeaderActions(): array
    {
        $currency = Workspace::restaurant()->currency;

        return [
            Action::make('refund')
                ->label(__('support.admin.actions.refund'))
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->ticket()->isOpen()
                    && Workspace::can(RestaurantPermission::RefundPayments)
                    && (app(RefundTicket::class)->refundable($this->ticket()) ?? 0) > 0)
                ->modalHeading(__('support.admin.refund.heading'))
                ->modalDescription(fn (): string => __('support.admin.refund.description', ['amount' => Money::format((int) app(RefundTicket::class)->refundable($this->ticket()), $currency)]))
                ->modalSubmitActionLabel(__('support.admin.actions.refund'))
                ->schema([
                    TextInput::make('amount')->label(__('support.admin.refund.amount'))
                        ->numeric()->minValue(0.01)->required()->suffix(Money::symbol($currency))
                        ->default(fn (): string => Money::toMajor((int) app(RefundTicket::class)->refundable($this->ticket()), $currency)),
                ])
                ->action(function (array $data) use ($currency): void {
                    $this->attempt(function () use ($data, $currency): void {
                        $refund = app(RefundTicket::class)->handle($this->ticket(), Workspace::user(), Money::toMinor((string) $data['amount'], $currency));
                        Notification::make()->success()->title(__('support.admin.refund.done', ['amount' => Money::format($refund->amount_minor, $currency)]))->send();
                    });
                }),
            Action::make('reward')
                ->label(__('support.admin.actions.reward'))
                ->icon('heroicon-m-gift')
                ->color('gray')
                ->visible(fn (): bool => $this->ticket()->isOpen())
                ->modalHeading(__('support.admin.reward.heading'))
                ->modalDescription(__('support.admin.reward.description'))
                ->modalSubmitActionLabel(__('support.admin.actions.reward'))
                ->schema([
                    Select::make('reward_id')->label(__('support.admin.reward.reward'))->required()->native(false)
                        ->options(fn (): array => Reward::query()->where('is_active', true)->whereNull('archived_at')->orderBy('id')->get()
                            ->mapWithKeys(fn (Reward $reward): array => [$reward->id => $reward->name.' · '.$reward->gives(Workspace::restaurant()->currency)])->all()),
                ])
                ->action(function (array $data): void {
                    $this->attempt(function () use ($data): void {
                        $reward = Reward::query()->findOrFail((int) $data['reward_id']);
                        app(RewardTicket::class)->handle($this->ticket(), Workspace::user(), $reward);
                        Notification::make()->success()->title(__('support.admin.reward.done', ['reward' => $reward->name]))->send();
                    });
                }),
            Action::make('resolve')
                ->label(__('support.admin.actions.resolve'))
                ->icon('heroicon-m-check-circle')
                ->color('primary')
                ->visible(fn (): bool => $this->ticket()->isOpen())
                ->modalHeading(__('support.admin.resolve.heading'))
                ->modalDescription(__('support.admin.resolve.description'))
                ->modalSubmitActionLabel(__('support.admin.actions.resolve'))
                ->schema([
                    Textarea::make('note')->label(__('support.admin.resolve.note'))->rows(3)->maxLength(TicketMessage::LENGTH),
                ])
                ->action(function (array $data): void {
                    $this->attempt(function () use ($data): void {
                        app(ResolveTicket::class)->handle($this->ticket(), Workspace::user(), $data['note'] ?? null);
                        Notification::make()->success()->title(__('support.admin.resolve.done'))->send();
                    });
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $ticket = $this->ticket()->load(['order.items', 'order.payment', 'customer', 'branch', 'messages.user', 'refund', 'walletReward.reward', 'resolver']);

        return [
            'ticket' => $ticket,
            'restaurant' => Workspace::restaurant(),
            'contacts' => Workspace::can(RestaurantPermission::ViewCustomerContacts),
        ];
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (TicketRefused $refused) {
            Notification::make()->danger()->title($refused->getMessage())->send();
        }

        $this->record = $this->ticket()->fresh() ?? $this->record;
    }

    private function ticket(): SupportTicket
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->getRecord();

        return $ticket;
    }
}
