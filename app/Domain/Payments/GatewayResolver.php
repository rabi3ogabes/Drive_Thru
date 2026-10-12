<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\ProviderClient;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Gateways\FakeGateway;
use App\Domain\Payments\Sadad\SadadGateway;
use App\Domain\Payments\Sadad\SadadSignature;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * Which payment provider a restaurant uses: its own enabled integration
 * (SADAD, QAR only), otherwise the Fake gateway outside production. When
 * neither applies, online payment is not offered and guests pay at the window.
 */
final class GatewayResolver
{
    /** @var array<int, ?PaymentGateway> */
    private array $memo = [];

    public function __construct(
        private readonly ProviderClient $client,
        private readonly SadadSignature $signature,
    ) {}

    public function for(Restaurant|int $restaurant): ?PaymentGateway
    {
        $restaurant = $restaurant instanceof Restaurant ? $restaurant : Restaurant::query()->findOrFail($restaurant);

        if (array_key_exists($restaurant->id, $this->memo)) {
            return $this->memo[$restaurant->id];
        }

        $integration = Integration::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->where('kind', IntegrationKind::Payments->value)
            ->where('is_enabled', true)
            ->first();

        $gateway = match (true) {
            $integration?->provider === SadadGateway::NAME && $restaurant->currency === 'QAR' => new SadadGateway($integration, $this->client, $this->signature),
            (bool) config('payments.fake_enabled') => new FakeGateway,
            default => null,
        };

        return $this->memo[$restaurant->id] = $gateway;
    }

    /** The gateway that handled a payment (by the name it recorded). */
    public function forPayment(Payment $payment): ?PaymentGateway
    {
        // Money taken at the window is settled at the window, never through a provider.
        if (! $payment->isOnline()) {
            return null;
        }

        $gateway = $this->for($payment->restaurant_id);

        if ($gateway !== null && $gateway->name() === $payment->gateway) {
            return $gateway;
        }

        return $payment->gateway === 'fake' && config('payments.fake_enabled') ? new FakeGateway : $gateway;
    }

    /**
     * What the guest can choose at checkout: the provider's online methods, then pay at the window.
     *
     * @return list<PaymentMethod>
     */
    public function methods(Restaurant $restaurant): array
    {
        return [...($this->for($restaurant)?->methods() ?? []), PaymentMethod::PayAtPickup];
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
