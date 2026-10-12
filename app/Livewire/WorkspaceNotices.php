<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Platform\Models\Announcement;
use App\Domain\Platform\PlatformSettings;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Notices at the top of every restaurant workspace: account suspension,
 * the platform-wide banner and dismissible announcements.
 *
 * @property-read ?Restaurant $restaurant
 * @property-read Collection<int, Announcement> $announcements
 * @property-read ?array{message: string, level: string} $banner
 */
final class WorkspaceNotices extends Component
{
    public ?int $restaurantId = null;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->restaurantId = $tenant instanceof Restaurant ? $tenant->id : null;
    }

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return $this->restaurantId !== null ? Restaurant::query()->find($this->restaurantId) : null;
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        $user = auth()->user();
        $restaurant = $this->restaurant;

        if (! $user instanceof User || $restaurant === null) {
            return collect();
        }

        return Announcement::query()->visibleTo($user, $restaurant)->limit(3)->get();
    }

    /**
     * @return array{message: string, level: string}|null
     */
    #[Computed]
    public function banner(): ?array
    {
        $settings = app(PlatformSettings::class);

        if (! $settings->bool('banner.enabled')) {
            return null;
        }

        $messages = (array) $settings->get('banner.message');
        $message = $messages[app()->getLocale()] ?? $messages['en'] ?? null;

        return filled($message) ? ['message' => (string) $message, 'level' => (string) $settings->get('banner.level', 'warning')] : null;
    }

    public function dismiss(int $announcementId): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $this->announcements->contains('id', $announcementId)) {
            return;
        }

        Announcement::query()->findOrFail($announcementId)
            ->dismissedBy()
            ->syncWithoutDetaching([$user->id => ['dismissed_at' => now()]]);

        unset($this->announcements);
    }

    public function render(): View
    {
        return view('livewire.workspace-notices');
    }
}
