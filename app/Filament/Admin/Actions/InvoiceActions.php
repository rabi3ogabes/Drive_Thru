<?php

declare(strict_types=1);

namespace App\Filament\Admin\Actions;

use App\Domain\Billing\Actions\MarkInvoicePaid;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Billing\Models\Invoice;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Invoice actions shared by the Invoices screen and the client page.
 */
final class InvoiceActions
{
    /** @return array<string, string> */
    public static function paymentMethods(): array
    {
        return [
            'bank_transfer' => __('billing.payment_methods.bank_transfer'),
            'card' => __('billing.payment_methods.card'),
            'cash' => __('billing.payment_methods.cash'),
            'cheque' => __('billing.payment_methods.cheque'),
            'other' => __('billing.payment_methods.other'),
        ];
    }

    public static function download(): Action
    {
        return Action::make('downloadPdf')
            ->label(__('billing.actions.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (Invoice $record): string => route('invoices.pdf', $record), shouldOpenInNewTab: true)
            ->visible(fn (Invoice $record): bool => Gate::allows('download', $record));
    }

    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label(__('billing.actions.mark_paid'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading(fn (Invoice $record): string => __('billing.actions.mark_paid_heading', ['number' => $record->number]))
            ->modalDescription(fn (Invoice $record): string => __('billing.actions.mark_paid_description', ['total' => $record->formattedTotal()]))
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->schema([
                Select::make('method')
                    ->label(__('billing.fields.payment_method'))
                    ->options(self::paymentMethods())
                    ->default('bank_transfer')
                    ->required()
                    ->native(false),
                TextInput::make('reference')
                    ->label(__('billing.fields.payment_reference'))
                    ->maxLength(120),
                DateTimePicker::make('paid_at')
                    ->label(__('billing.fields.paid_at'))
                    ->default(now())
                    ->maxDate(now()->addMinute())
                    ->native(false)
                    ->required(),
            ])
            ->visible(fn (Invoice $record): bool => Gate::allows('markPaid', $record))
            ->action(function (Invoice $record, array $data): void {
                try {
                    app(MarkInvoicePaid::class)->handle($record, $data['method'], $data['reference'] ?? null, CarbonImmutable::parse($data['paid_at']));
                } catch (DomainException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('billing.notifications.paid', ['number' => $record->number]))->send();
            });
    }

    public static function void(): Action
    {
        return Action::make('voidInvoice')
            ->label(__('billing.actions.void'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Invoice $record): string => __('billing.actions.void_heading', ['number' => $record->number]))
            ->modalDescription(__('billing.actions.void_description'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('billing.fields.void_reason'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(300),
            ])
            ->visible(fn (Invoice $record): bool => Gate::allows('void', $record))
            ->action(function (Invoice $record, array $data): void {
                try {
                    app(VoidInvoice::class)->handle($record, $data['reason']);
                } catch (DomainException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('billing.notifications.voided', ['number' => $record->number]))->send();
            });
    }
}
