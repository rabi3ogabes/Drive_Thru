<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Customers\Models\Customer;
use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\ProviderCall;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Actions\ExpireUnpaidOrders;
use App\Domain\Payments\Actions\RefundPayment;
use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\GatewayResolver;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Sadad\SadadGateway;
use App\Domain\Payments\Sadad\SadadSignature;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

const SADAD_TEST_KEY = 'test-secret-key-123';

function sadadRestaurant(string $currency = 'QAR'): Restaurant
{
    $restaurant = restaurant();
    $restaurant->forceFill(['currency' => $currency, 'contact_email' => 'orders@masar.test'])->save();

    Integration::withoutTenancy()->create([
        'restaurant_id' => $restaurant->id,
        'kind' => IntegrationKind::Payments,
        'provider' => 'sadad',
        'mode' => 'test',
        'is_enabled' => true,
        'credentials' => [
            'merchant_id' => '7015085',
            'website' => 'masar.test',
            'secret_key_test' => SADAD_TEST_KEY,
            'secret_key_live' => 'live-key-never-used-here',
            'api_sadad_id' => '9288463',
            'api_key_test' => 'api-test-key',
            'api_domain' => 'masar.test',
        ],
    ]);

    return $restaurant->fresh();
}

/** An online order with a SADAD payment started; returns [order, payment]. */
function sadadOrder(Restaurant $restaurant): array
{
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail '.Str::random(3), 'ar' => 'لوسيل']]);
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2500]);
    $order = app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 2]],
        paymentMethod: PaymentMethod::Card, idempotencyKey: (string) Str::uuid(),
    ));
    app(StartPayment::class)->handle($order, PaymentMethod::Card);

    return [$order->fresh(), Payment::withoutTenancy()->where('order_id', $order->id)->sole()];
}

/** Signs like SADAD does (independently of the app's class): key + values sorted by key, SHA-256. */
function sadadSign(array $params, string $key = SADAD_TEST_KEY): string
{
    ksort($params);

    return hash('sha256', $key.implode('', array_map('strval', $params)));
}

function sadadCallback(Payment $payment, int $status, ?string $amount = null): array
{
    $params = [
        'MID' => '7015085',
        'ORDERID' => $payment->reference,
        'RESPCODE' => $status === 3 ? '1' : '400',
        'RESPMSG' => $status === 3 ? 'Txn Success' : 'Declined by bank',
        'STATUS' => $status === 3 ? 'TXN_SUCCESS' : 'TXN_FAILURE',
        'TXNAMOUNT' => $amount ?? number_format($payment->amount_minor / 100, 2, '.', ''),
        'transaction_number' => 'SD3300000001',
        'transaction_status' => (string) $status,
    ];

    return $params + ['checksumhash' => sadadSign($params)];
}

it('signs exactly as SADAD documents, and verifies callbacks either case', function () {
    $signature = new SadadSignature;
    $params = ['txnDate' => '2025-12-16 12:30:00', 'merchant_id' => '123456', 'WEBSITE' => 'MYSHOP', 'ORDER_ID' => 'ORD-1', 'TXN_AMOUNT' => '150.00'];

    expect($signature->sign($params, 'KEY'))->toBe(strtoupper(sadadSign($params, 'KEY')))
        ->and($signature->verify($params + ['checksumhash' => sadadSign($params, 'KEY')], 'KEY'))->toBeTrue()
        ->and($signature->verify($params + ['checksumhash' => strtoupper(sadadSign($params, 'KEY'))], 'KEY'))->toBeTrue()
        ->and($signature->verify($params + ['checksumhash' => sadadSign($params, 'OTHER')], 'KEY'))->toBeFalse()
        ->and($signature->verify($params, 'KEY'))->toBeFalse();
});

it('sends the guest to SADAD with a signed form and only offers card online', function () {
    $restaurant = sadadRestaurant();
    [$order, $payment] = sadadOrder($restaurant);

    expect(app(GatewayResolver::class)->methods($restaurant))->toBe([PaymentMethod::Card, PaymentMethod::PayAtPickup])
        ->and($payment->gateway)->toBe('sadad')
        ->and($payment->mode)->toBe('test')
        ->and($payment->redirect_url)->toBe(route('payments.sadad.checkout', $payment->reference));

    $page = $this->get($payment->redirect_url)->assertOk()
        ->assertSee('action="https://sadadqa.com/webpurchase"', false)
        ->assertSee('name="ORDER_ID" value="'.$payment->reference.'"', false)
        ->assertSee('name="TXN_AMOUNT" value="50.00"', false)
        ->assertSee('name="MOBILE_NO" value="97455550001"', false)
        ->assertDontSee(SADAD_TEST_KEY);

    preg_match_all('/name="([^"]+)" value="([^"]*)"/', $page->getContent(), $m);
    $fields = array_combine($m[1], array_map('html_entity_decode', $m[2]));
    $signature = $fields['signature'];
    unset($fields['signature']);

    expect($signature)->toBe(strtoupper(sadadSign($fields)));
});

it('marks the order paid from a genuine callback, and ignores forged or wrong-amount ones', function () {
    [$order, $payment] = sadadOrder(sadadRestaurant());

    $forged = sadadCallback($payment, 3);
    $forged['checksumhash'] = sadadSign(['tampered' => 'yes']);
    $this->post(route('payments.sadad.callback'), $forged)->assertRedirect(route('customer.order', [$order->restaurant, $order]));
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    $this->post(route('payments.sadad.callback'), sadadCallback($payment, 3, '1.00'));
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    $this->post(route('payments.sadad.callback'), sadadCallback($payment, 3))->assertRedirect(route('customer.order', [$order->restaurant, $order]));

    expect($payment->fresh())->status->toBe(PaymentStatus::Paid)->provider_transaction->toBe('SD3300000001')
        ->and($order->fresh())->status->toBe(OrderStatus::Received)->payment_status->toBe(PaymentStatus::Paid)
        ->and(ProviderCall::withoutTenancy()->where('operation', 'callback')->where('ok', false)->pluck('error')->all())->toBe(['invalid_checksum', 'amount_mismatch']);
});

it('settles from the webhook, answers success every time, and ignores test/live mix-ups', function () {
    [$order, $payment] = sadadOrder(sadadRestaurant());
    $body = ['isTestMode' => 1, 'merchantId' => '7015085', 'message' => 'success', 'transactionNumber' => 'SD3300000009', 'transactionStatus' => 3, 'txnAmount' => 50, 'websiteRefNo' => $payment->reference];

    $live = ['isTestMode' => 0] + $body;
    $this->postJson(route('payments.sadad.webhook'), $live + ['checksumhash' => sadadSign($live)])->assertOk()->assertExactJson(['status' => 'success']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    // SADAD signed "50.00"; JSON delivers the number 50.
    $signed = ['txnAmount' => '50.00'] + $body;
    $this->postJson(route('payments.sadad.webhook'), $body + ['checksumhash' => sadadSign($signed)])->assertOk()->assertExactJson(['status' => 'success']);
    $this->postJson(route('payments.sadad.webhook'), $body + ['checksumhash' => sadadSign($signed)])->assertOk();

    expect($payment->fresh())->status->toBe(PaymentStatus::Paid)->provider_transaction->toBe('SD3300000009')
        ->and($order->fresh()->history()->where('to', 'received')->count())->toBe(1);

    $this->postJson(route('payments.sadad.webhook'), ['websiteRefNo' => 'nope', 'checksumhash' => 'x'])->assertOk()->assertExactJson(['status' => 'success']);
});

it('records a declined payment so the guest can try again', function () {
    [$order, $payment] = sadadOrder(sadadRestaurant());
    $this->post(route('payments.sadad.callback'), sadadCallback($payment, 2));

    expect($payment->fresh())->status->toBe(PaymentStatus::Failed)->failure_reason->toBe('Declined by bank')
        ->and($order->fresh())->status->toBe(OrderStatus::PendingPayment)->payment_status->toBe(PaymentStatus::Failed);
});

it('refunds through the SADAD API when a paid order is cancelled', function () {
    Http::fake([
        'api-sandbox.sadad.qa/api/userbusinesses/login' => Http::response(['accessToken' => 'TOKEN-1']),
        'api-sandbox.sadad.qa/api/transactions/refundTransaction' => Http::response(['isRefund' => true, 'invoicenumber' => 'SD33REFUND1', 'amount' => 50]),
    ]);
    [$order, $payment] = sadadOrder(sadadRestaurant());
    $this->post(route('payments.sadad.callback'), sadadCallback($payment, 3));

    app(CancelOrder::class)->bySystem($order->fresh(), 'kitchen_closed');

    $refund = Refund::withoutTenancy()->sole();
    expect($refund)->status->toBe(Refund::SUCCEEDED)->amount_minor->toBe(5000)->provider_reference->toBe('SD33REFUND1')
        ->and($payment->fresh())->status->toBe(PaymentStatus::Refunded)->refunded_minor->toBe(5000)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/userbusinesses/login') && $request['sadadId'] === 9288463 && $request['secretKey'] === 'api-test-key');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refundTransaction') && $request->header('Authorization') === ['TOKEN-1'] && $request['transactionno'] === 'SD3300000001' && $request['amount'] === 50.0);
});

it('keeps a failed refund visible and lets staff try again; SADAD refunds in full only', function () {
    Http::fake([
        'api-sandbox.sadad.qa/api/userbusinesses/login' => Http::response(['accessToken' => 'TOKEN-1']),
        'api-sandbox.sadad.qa/api/transactions/refundTransaction' => Http::sequence()->push(['message' => 'Transaction not found'], 404)->push(['isRefund' => true]),
    ]);
    [, $payment] = sadadOrder(sadadRestaurant());
    $this->post(route('payments.sadad.callback'), sadadCallback($payment, 3));

    expect(fn () => app(RefundPayment::class)->handle($payment->fresh(), 1000, 'Missing fries', 'staff:1'))->toThrow(InvalidArgumentException::class);

    $failed = app(RefundPayment::class)->handle($payment->fresh(), null, 'Wrong order', 'staff:1');
    expect($failed)->status->toBe(Refund::FAILED)->failure_reason->toBe('Transaction not found')
        ->and($payment->fresh())->status->toBe(PaymentStatus::Paid)->refunded_minor->toBe(0);

    $retry = app(RefundPayment::class)->handle($payment->fresh(), null, 'Wrong order', 'staff:1');
    expect($retry->status)->toBe(Refund::SUCCEEDED)->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('settles or cancels unpaid orders after the timeout by asking SADAD', function () {
    Http::fake([
        'api-sandbox.sadad.qa/api/userbusinesses/login' => Http::response(['accessToken' => 'TOKEN-1']),
        'api-sandbox.sadad.qa/api/transactions/getTransaction*' => Http::sequence()
            ->push(['transactionstatusId' => 3, 'amount' => 50, 'servicecharge' => 1.25])
            ->push(['transactionstatusId' => 1, 'amount' => 50]),
    ]);
    $restaurant = sadadRestaurant();
    [$paid, $paidPayment] = sadadOrder($restaurant);
    [$inProgress, $progressPayment] = sadadOrder($restaurant);
    [$abandoned] = sadadOrder($restaurant);
    $paidPayment->forceFill(['provider_transaction' => 'SD1'])->save();
    $progressPayment->forceFill(['provider_transaction' => 'SD2'])->save();

    $this->travel(25)->minutes();
    $counts = app(ExpireUnpaidOrders::class)->handle();

    expect($counts)->toBe(['paid' => 1, 'cancelled' => 1, 'waiting' => 1])
        ->and($paid->fresh()->status)->toBe(OrderStatus::Received)
        ->and($paidPayment->fresh()->fee_minor)->toBe(125)
        ->and($inProgress->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($abandoned->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($abandoned->fresh()->cancel_reason)->toBe('payment_timeout');
});

it('refunds money that arrives after the order was cancelled', function () {
    Http::fake([
        'api-sandbox.sadad.qa/api/userbusinesses/login' => Http::response(['accessToken' => 'TOKEN-1']),
        'api-sandbox.sadad.qa/api/transactions/refundTransaction' => Http::response(['isRefund' => true, 'invoicenumber' => 'SD33LATE']),
    ]);
    [$order, $payment] = sadadOrder(sadadRestaurant());
    app(CancelOrder::class)->bySystem($order, 'payment_timeout');

    $this->post(route('payments.sadad.callback'), sadadCallback($payment, 3));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(Refund::withoutTenancy()->sole()->reason)->toBe('order_cancelled_before_payment')
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('uses SADAD only for QAR restaurants, and nothing online in production without a provider', function () {
    expect(app(GatewayResolver::class)->for(sadadRestaurant('USD')))->not->toBeInstanceOf(SadadGateway::class);

    config(['payments.fake_enabled' => false]);
    app(GatewayResolver::class)->forget();
    $plain = restaurant();

    expect(app(GatewayResolver::class)->for($plain))->toBeNull()
        ->and(app(GatewayResolver::class)->methods($plain))->toBe([PaymentMethod::PayAtPickup]);
});

it('checks the API connection from settings', function () {
    Http::fake(['api-sandbox.sadad.qa/api/userbusinesses/login' => Http::sequence()->push(['accessToken' => 'T'])->push(['message' => 'Invalid credentials !'], 401)]);
    $restaurant = sadadRestaurant();
    $gateway = app(GatewayResolver::class)->for($restaurant);

    expect($gateway)->toBeInstanceOf(SadadGateway::class)
        ->and($gateway->verifyConnection())->toBeTrue()
        ->and($gateway->verifyConnection())->toBeFalse()
        ->and(ProviderCall::withoutTenancy()->where('provider', 'sadad')->where('operation', 'login')->pluck('ok')->all())->toBe([true, false]);
});
