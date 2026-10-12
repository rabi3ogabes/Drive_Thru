<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Health\ProviderHealth;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Filament\Admin\AdminNavigationGroup;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Platform health: how each outside provider (payments, WhatsApp, SMS,
 * push, printing, POS, webhooks) is doing across all restaurants, and
 * whether the platform-level ones are set up at all.
 */
class PlatformHealth extends Page
{
    protected string $view = 'filament.admin.pages.platform-health';

    protected static ?string $slug = 'health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Platform;

    protected static ?int $navigationSort = 8;

    #[Url]
    public string $period = '24h';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPlatformPermission(PlatformPermission::ManageSettings);
    }

    public static function getNavigationLabel(): string
    {
        return __('platform.health.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform.health.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform.health.subheading');
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['1h', '24h', '7d'], true) ? $period : '24h';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function providers(): array
    {
        $since = match ($this->period) {
            '1h' => now()->subHour(),
            '7d' => now()->subDays(7),
            default => now()->subDay(),
        };

        return app(ProviderHealth::class)->summary(CarbonImmutable::instance($since));
    }

    /**
     * Platform-level setup (from .env), and how many restaurants connected their own accounts.
     *
     * @return list<array{key: string, ok: bool, detail: string}>
     */
    public function setup(): array
    {
        $connected = Integration::withoutTenancy()->where('is_enabled', true)->groupBy('kind')->pluck(DB::raw('count(*)'), 'kind');
        $pendingJobs = DB::table('jobs')->where('available_at', '<', now()->subMinutes(5)->getTimestamp())->count();

        return [
            ['key' => 'whatsapp', 'ok' => filled(config('messaging.whatsapp.phone_number_id')) && filled(config('messaging.whatsapp.access_token')), 'detail' => ''],
            ['key' => 'sms', 'ok' => filled(config('messaging.smsala.api_token')), 'detail' => ''],
            ['key' => 'webpush', 'ok' => filled(config('webpush.public_key')) && filled(config('webpush.private_key')), 'detail' => ''],
            ['key' => 'mail', 'ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'detail' => (string) config('mail.default')],
            ['key' => 'queue', 'ok' => config('queue.default') === 'sync' || $pendingJobs === 0, 'detail' => (string) $pendingJobs],
            ['key' => 'payments', 'ok' => true, 'detail' => (string) ($connected[IntegrationKind::Payments->value] ?? 0)],
            ['key' => 'webhooks_failed', 'ok' => ($failed = WebhookDelivery::withoutTenancy()->where('status', WebhookDelivery::FAILED)->where('created_at', '>=', now()->subDay())->count()) === 0, 'detail' => (string) $failed],
        ];
    }
}
