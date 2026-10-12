<?php

use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Customers\Models\Customer;
use App\Domain\Integrations\Enums\IntegrationKind;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Menu\Models\Product;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Data\OrderRequest;
use App\Domain\Ordering\Enums\PaymentMethod;
use App\Domain\Ordering\Models\Order;
use App\Domain\Printing\Jobs\PrintOrderJob;
use App\Domain\Printing\PrintOrder;
use App\Domain\Printing\TicketRenderer;
use App\Http\Middleware\AuthenticateDevice;
use App\Livewire\Ops\KitchenDisplay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function printBranch(array $attributes = []): Branch
{
    $branch = app(CreateBranch::class)->handle(restaurant(), ['name' => ['en' => 'Lusail', 'ar' => 'لوسيل']]);
    $branch->forceFill($attributes)->save();

    return $branch->fresh();
}

function printOrder(Branch $branch): Order
{
    $customer = Customer::withoutTenancy()->firstOrCreate(['restaurant_id' => $branch->restaurant_id, 'phone' => '+97455550001'], ['name' => 'Sara']);
    $product = Product::factory()->create(['restaurant_id' => $branch->restaurant_id, 'price_minor' => 2500, 'name' => ['en' => 'Classic Burger', 'ar' => 'برجر كلاسيك']]);

    return app(PlaceOrder::class)->handle(new OrderRequest(
        customer: $customer, branch: $branch,
        lines: [['key' => 'a', 'product_id' => $product->id, 'quantity' => 2, 'notes' => 'No onions']],
        paymentMethod: PaymentMethod::PayAtPickup, idempotencyKey: (string) Str::uuid(),
    ));
}

function withPrintNode(Branch $branch): void
{
    Integration::withoutTenancy()->create([
        'restaurant_id' => $branch->restaurant_id, 'kind' => IntegrationKind::Printing, 'provider' => 'printnode',
        'mode' => 'live', 'is_enabled' => true, 'credentials' => ['api_key' => 'pn-key'],
    ]);
}

it('renders 80 mm kitchen tickets and receipts in the right language', function () {
    $order = printOrder(printBranch());
    $tickets = app(TicketRenderer::class);
    $locale = app()->getLocale();

    $kitchen = $tickets->html($order, TicketRenderer::KITCHEN);
    expect($kitchen)->toContain('size: 80mm auto')->toContain($order->displayNumber())->toContain('No onions')->not->toContain(__('guest.checkout.total'));

    $order->forceFill(['locale' => 'ar'])->save();
    $receipt = $tickets->html($order->fresh(), TicketRenderer::RECEIPT);
    expect($receipt)->toContain('dir="rtl"')->toContain('برجر كلاسيك')->toContain(__('guest.checkout.total', [], 'ar'))
        ->and(app()->getLocale())->toBe($locale);

    expect(substr($tickets->pdf($order, TicketRenderer::KITCHEN), 0, 4))->toBe('%PDF');
});

it('sends tickets to PrintNode when the branch has a printer, otherwise prints in the browser', function () {
    Http::fake(['api.printnode.com/printjobs' => Http::response('123456', 201)]);
    $branch = printBranch(['printers' => ['kitchen' => '70001']]);
    $order = printOrder($branch);

    expect(app(PrintOrder::class)->handle($order, TicketRenderer::KITCHEN))->toBe(PrintOrder::BROWSER);

    withPrintNode($branch);
    expect(app(PrintOrder::class)->handle($order, TicketRenderer::KITCHEN))->toBe(PrintOrder::SENT)
        ->and(app(PrintOrder::class)->handle($order, TicketRenderer::RECEIPT))->toBe(PrintOrder::BROWSER);

    Http::assertSent(fn ($request) => $request['printerId'] === 70001 && $request['contentType'] === 'pdf_base64'
        && str_starts_with(base64_decode($request['content']), '%PDF') && $request->hasHeader('Authorization', 'Basic '.base64_encode('pn-key:')));
});

it('prints from the kitchen screen: printer first, browser as the fallback', function () {
    Http::fake(['api.printnode.com/printjobs' => Http::response(['error' => 'offline'], 503)]);
    $branch = printBranch(['printers' => ['kitchen' => '70001']]);
    withPrintNode($branch);
    $order = printOrder($branch);
    $code = app(CreateDevice::class)->handle($branch, DeviceKind::Kitchen, 'KDS');
    $token = pairedToken($this, $code->formatted());

    Livewire::withCookie(AuthenticateDevice::COOKIE, $token)
        ->test(KitchenDisplay::class, ['branchId' => $branch->id, 'deviceId' => Device::withoutTenancy()->latest('id')->value('id')])
        ->call('printTicket', $order->id, 'kitchen')
        ->assertDispatched('ops-toast', tone: 'error')
        ->assertDispatched('ops-print', url: route('print.ticket', ['code' => $order->short_code, 'kind' => 'kitchen']));

    $this->withCookie(AuthenticateDevice::COOKIE, $token)->get(route('print.ticket', ['code' => $order->short_code, 'kind' => 'kitchen']))
        ->assertOk()->assertSee('window.print()', false);
});

it('opens printable tickets only for the branch’s screens and staff', function () {
    $order = printOrder(printBranch());
    $url = route('print.ticket', ['code' => $order->short_code, 'kind' => 'receipt']);

    $this->get($url)->assertForbidden();
    $this->actingAs(employeeAt(restaurant(['en' => 'Other', 'ar' => 'آخر']), 'general_manager', ['all_branches' => true]))->get($url)->assertForbidden();
    $this->actingAs(ownerOf($order->restaurant))->get($url)->assertOk()->assertSee($order->displayNumber());
});

it('prints a kitchen ticket automatically when the branch asks for it', function () {
    Queue::fake([PrintOrderJob::class]);

    printOrder(printBranch());
    Queue::assertNotPushed(PrintOrderJob::class);

    $order = printOrder(printBranch(['auto_print' => true]));
    Queue::assertPushed(PrintOrderJob::class, fn (PrintOrderJob $job) => $job->orderId === $order->id && $job->kind === 'kitchen');
});
