<?php

declare(strict_types=1);

namespace App\Domain\Billing\Notifications;

use App\Domain\Billing\InvoicePdf;
use App\Domain\Billing\Models\Invoice;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;

        return (new MailMessage)
            ->subject(__('billing.mail.invoice_issued.subject', ['number' => $invoice->number]))
            ->greeting(__('billing.mail.greeting'))
            ->line(__('billing.mail.invoice_issued.body', [
                'number' => $invoice->number,
                'total' => $invoice->formattedTotal(),
                'due' => $invoice->due_at->timezone(config('app.display_timezone'))->format('Y-m-d'),
            ]))
            ->line(__('billing.mail.invoice_issued.attachment'))
            ->attachData(app(InvoicePdf::class)->render($invoice), $invoice->number.'.pdf', ['mime' => 'application/pdf'])
            ->salutation(__('billing.mail.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('billing.mail.invoice_issued.subject', ['number' => $this->invoice->number]))
            ->body($this->invoice->formattedTotal())
            ->icon('heroicon-o-document-text')
            ->warning()
            ->getDatabaseMessage();
    }
}
