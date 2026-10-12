<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Customers\Actions\SendOtp;
use App\Domain\Customers\Exceptions\OtpProblem;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\TransitionOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'messaging.log_when_unconfigured' => false,
        'messaging.whatsapp.phone_number_id' => '1234567890',
        'messaging.whatsapp.access_token' => 'wa-token',
        'messaging.whatsapp.app_secret' => 'wa-app-secret',
        'messaging.whatsapp.verify_token' => 'verify-me',
        'messaging.smsala.api_token' => 'sms-token',
        'messaging.smsala.sender_id' => 'MASAR',
    ]);
});

function waOk(string $id = 'wamid.ABC'): array
{
    return ['messaging_product' => 'whatsapp', 'messages' => [['id' => $id]]];
}

function messagingOrder(string $locale = 'en'): Order
{
    $restaurant = restaurant();
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $customer = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001', 'name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id, 'price_minor' => 2500]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(), locale: $locale,
    ));
}

it('sends login codes with the WhatsApp authentication template', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(waOk())]);
    $restaurant = restaurant();

    $sent = app(SendOtp::class)->handle($restaurant, '5555 1234', '10.0.0.1');

    expect($sent['channel'])->toBe('whatsapp')
        ->and(MessageDelivery::withoutTenancy()->sole())->status->toBe('sent')->provider_message_id->toBe('wamid.ABC')->purpose->toBe('otp')->to_masked->toBe('+974••••34');

    Http::assertSent(function (Request $request) use ($sent) {
        $code = $sent['demo_code'];

        return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer wa-token')
            && $request['to'] === '97455551234'
            && $request['template']['name'] === 'dtp_login_code'
            && $request['template']['components'][0]['parameters'][0]['text'] === $code
            && $request['template']['components'][1]['sub_type'] === 'url'
            && $request['template']['components'][1]['parameters'][0]['text'] === $code;
    });
});

it('falls back to SMSala when WhatsApp fails, in Arabic with UCS-2', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Recipient not on WhatsApp']], 400),
        'api2.smsala.com/SendSmsV2' => Http::response([['Status' => 'Success', 'OperationCode' => 0, 'Remarks' => 'Message Submitted', 'MessageId' => 25]]),
    ]);
    app()->setLocale('ar');

    $sent = app(SendOtp::class)->handle(restaurant(), '55551234', '10.0.0.1');

    expect($sent['channel'])->toBe('sms')
        ->and(MessageDelivery::withoutTenancy()->orderBy('id')->pluck('status', 'channel')->all())->toBe(['whatsapp' => 'failed', 'sms' => 'sent'])
        ->and(MessageDelivery::withoutTenancy()->where('channel', 'whatsapp')->value('error'))->toBe('Recipient not on WhatsApp');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/SendSmsV2')
        && $request['apiToken'] === 'sms-token' && $request['messageType'] === '3' && $request['messageEncoding'] === '8'
        && $request['sourceAddress'] === 'MASAR' && $request['destinationAddress'] === '97455551234'
        && str_contains($request['messageText'], $sent['demo_code']) && str_contains($request['callBackUrl'], 'signature='));
});

it('refuses to pretend a code was sent when no channel works', function () {
    config(['messaging.whatsapp.access_token' => null, 'messaging.smsala.api_token' => null]);

    expect(fn () => app(SendOtp::class)->handle(restaurant(), '55551234', '10.0.0.1'))->toThrow(OtpProblem::class, __('customers.otp.errors.delivery'));
});

it('tracks WhatsApp delivery from Meta’s signed webhook only', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(waOk('wamid.XYZ'))]);
    app(SendOtp::class)->handle(restaurant(), '55551234', '10.0.0.1');

    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertOk()->assertSee('42');
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();

    $payload = ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.XYZ', 'status' => 'read']]]]]]]];
    $body = json_encode($payload);

    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertForbidden();
    expect(MessageDelivery::withoutTenancy()->sole()->status)->toBe('sent');

    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'wa-app-secret')], $body)->assertOk();
    expect(MessageDelivery::withoutTenancy()->sole()->status)->toBe('read');

    // A late "delivered" never moves it back.
    $late = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.XYZ', 'status' => 'delivered']]]]]]]]);
    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $late, 'wa-app-secret')], $late)->assertOk();
    expect(MessageDelivery::withoutTenancy()->sole()->status)->toBe('read');
});

it('tracks SMS delivery from SMSala’s signed callback only', function () {
    config(['messaging.whatsapp.access_token' => null]);
    Http::fake(['api2.smsala.com/*' => Http::response([['Status' => 'Success', 'MessageId' => 7]])]);
    app(SendOtp::class)->handle(restaurant(), '55551234', '10.0.0.1');
    $delivery = MessageDelivery::withoutTenancy()->sole();

    $this->postJson("/webhooks/smsala/{$delivery->id}", ['dlrStatus' => 'Delivered'])->assertForbidden();
    $this->postJson(URL::signedRoute('webhooks.smsala', ['delivery' => $delivery->id]), ['messageId' => 7, 'dlrStatus' => 'Delivered'])->assertOk();

    expect($delivery->fresh()->status)->toBe('delivered');
});

it('messages the guest when the order is received, ready and picked up, once each, in their language', function () {
    Http::fake(['graph.facebook.com/*' => Http::sequence()->push(waOk('w1'))->push(waOk('w2'))->push(waOk('w3'))]);
    $order = messagingOrder('ar');

    app(TransitionOrder::class)->handle($order, OrderStatus::Ready, TransitionOrder::STAFF, 1);
    app(TransitionOrder::class)->handle($order, OrderStatus::PickedUp, TransitionOrder::STAFF, 1);

    expect(MessageDelivery::withoutTenancy()->orderBy('id')->pluck('purpose')->all())->toBe(['order_received', 'order_ready', 'receipt']);

    Http::assertSent(fn (Request $r) => $r['template']['name'] === 'dtp_order_received' && $r['template']['language']['code'] === 'ar'
        && $r['template']['components'][0]['parameters'][1]['text'] === $order->displayNumber());
    Http::assertSent(fn (Request $r) => $r['template']['name'] === 'dtp_order_ready' && $r['template']['components'][0]['parameters'][1]['text'] === 'لوسيل');
    Http::assertSent(fn (Request $r) => $r['template']['name'] === 'dtp_order_receipt' && str_contains($r['template']['components'][0]['parameters'][2]['text'], '/o/'.$order->short_code));

    // A reset back to "received" doesn't message again.
    app(TransitionOrder::class)->resetAsNew($order->fresh(), TransitionOrder::STAFF, 1, 'Remake');
    expect(MessageDelivery::withoutTenancy()->where('purpose', 'order_received')->count())->toBe(1);
});

it('respects guests who turned order messages off', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(waOk())]);
    $restaurant = restaurant();
    Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001', 'name' => 'Sara', 'notification_prefs' => ['order_updates' => false, 'receipts' => true]]);
    $branch = app(CreateBranch::class)->handle($restaurant, ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);
    $order = app(PlaceOrder::class)->handle(new OrderRequest(
        customer: Customer::withoutTenancy()->sole(), branch: $branch, lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 1]],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));

    app(TransitionOrder::class)->handle($order, OrderStatus::Ready, TransitionOrder::STAFF, 1);
    app(TransitionOrder::class)->handle($order, OrderStatus::PickedUp, TransitionOrder::STAFF, 1);

    expect(MessageDelivery::withoutTenancy()->pluck('purpose')->all())->toBe(['receipt']);
});
