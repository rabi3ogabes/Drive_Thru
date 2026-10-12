<?php

declare(strict_types=1);

namespace App\Domain\Menu\Notifications;

use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\Models\Product;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A bell alert to the people who run the branch when an item's daily stock
 * runs low, sent once per day per item.
 */
final class LowStock extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly Branch $branch,
        public readonly int $left,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(trans_choice('menu.notifications.low_stock', $this->left, ['product' => $this->product->name, 'count' => $this->left]))
            ->body($this->branch->name)
            ->icon('heroicon-o-archive-box-x-mark')
            ->warning()
            ->getDatabaseMessage();
    }
}
