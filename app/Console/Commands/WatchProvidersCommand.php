<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integrations\Health\ProviderHealth;
use App\Filament\Admin\Pages\PlatformHealth;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Localizable;

/**
 * Every five minutes: a provider failing most of its calls in the last 15
 * minutes alerts the super admins (in their language), at most once an hour.
 */
final class WatchProvidersCommand extends Command
{
    use Localizable;

    protected $signature = 'providers:watch';

    protected $description = 'Alert platform admins when an outside provider keeps failing';

    public function handle(ProviderHealth $health): int
    {
        $down = array_filter(
            $health->summary(now()->toImmutable()->subMinutes(15)),
            fn (array $row): bool => $row['status'] === ProviderHealth::DOWN,
        );

        foreach ($down as $row) {
            if (! Cache::add('provider-down:'.$row['provider'], true, now()->addHour())) {
                continue;
            }

            User::query()->where('is_super_admin', true)->where('is_active', true)->each(function (User $admin) use ($row): void {
                $this->withLocale($admin->locale ?? config('app.locale'), function () use ($admin, $row): void {
                    Notification::make()
                        ->title(__('platform.health.alert.title', ['provider' => __('platform.health.providers.'.$row['provider'])]))
                        ->body(__('platform.health.alert.body', ['failures' => $row['failures'], 'calls' => $row['calls'], 'error' => $row['last_error'] ?? '—']))
                        ->icon('heroicon-o-signal-slash')
                        ->danger()
                        ->actions([Action::make('open')->label(__('platform.health.alert.open'))->url(PlatformHealth::getUrl(panel: 'admin'))])
                        ->sendToDatabase($admin);
                });
            });
        }

        $this->components->info(count($down).' provider(s) failing.');

        return self::SUCCESS;
    }
}
