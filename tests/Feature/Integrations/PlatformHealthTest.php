<?php

use App\Domain\Integrations\Health\ProviderHealth;
use App\Domain\Integrations\Models\ProviderCall;
use App\Filament\Admin\Pages\PlatformHealth;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function providerCalls(string $provider, int $ok, int $failed, ?int $restaurantId = null): void
{
    $restaurantId ??= restaurant(slug: 'r-'.$provider)->id;

    foreach (range(1, $ok + $failed) as $i) {
        ProviderCall::withoutTenancy()->create([
            'restaurant_id' => $restaurantId,
            'provider' => $provider,
            'operation' => 'send',
            'ok' => $i <= $ok,
            'http_status' => $i <= $ok ? 200 : 503,
            'duration_ms' => 100 * $i,
            'error' => $i <= $ok ? null : 'Service unavailable',
            'created_at' => now(),
        ]);
    }
}

it('summarises each provider and marks failing ones', function () {
    $restaurant = restaurant();
    providerCalls('whatsapp', 9, 1, $restaurant->id);
    providerCalls('smsala', 1, 4, $restaurant->id);

    $rows = collect(app(ProviderHealth::class)->summary(now()->toImmutable()->subDay()))->keyBy('provider');

    expect($rows['whatsapp'])->toMatchArray(['calls' => 10, 'failures' => 1, 'success_rate' => 90.0, 'status' => ProviderHealth::DEGRADED, 'restaurants' => 1])
        ->and($rows['smsala'])->toMatchArray(['status' => ProviderHealth::DOWN, 'last_error' => 'HTTP 503 · Service unavailable'])
        ->and($rows['sadad'])->toMatchArray(['calls' => 0, 'status' => ProviderHealth::IDLE]);
});

it('alerts super admins about a failing provider once an hour', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    providerCalls('smsala', 0, 5);

    $this->artisan('providers:watch')->expectsOutputToContain('1 provider(s) failing');
    $this->artisan('providers:watch');

    expect($admin->notifications()->count())->toBe(1)
        ->and($admin->notifications()->first()->data['title'])->toContain('SMS');
});

it('shows the health page to platform admins only', function () {
    providerCalls('webhook', 3, 0);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(PlatformHealth::class)
        ->assertSee(__('platform.health.providers.webhook'))
        ->call('setPeriod', '7d')
        ->assertSet('period', '7d')
        ->assertSee(__('platform.health.setup.webpush.label'));

    $this->get(PlatformHealth::getUrl(panel: 'admin'))->assertOk();

    $this->actingAs(ownerOf(restaurant()))->get(PlatformHealth::getUrl(panel: 'admin'))->assertForbidden();
});
