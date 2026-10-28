<?php

use App\Domain\Integrations\Api\ApiScope;
use App\Domain\Integrations\Api\OpenApi;
use App\Domain\Integrations\Webhooks\WebhookEvent;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Http\Controllers\DeviceScreenController;
use App\Http\Controllers\DownloadInvoiceController;
use App\Http\Controllers\DownloadQrController;
use App\Http\Controllers\FakePaymentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeaveImpersonationController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MessagingWebhookController;
use App\Http\Controllers\OpsController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RealtimeAuthController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SadadController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SubmitLeadController;
use App\Http\Controllers\SwitchLocaleController;
use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\ResolveGuestRestaurant;
use App\Livewire\Customer;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/pricing', [SiteController::class, 'pricing'])->name('site.pricing');
Route::get('/services/{service}', [SiteController::class, 'service'])->whereIn('service', ['drive-thru', 'ready-call', 'rewards'])->name('site.service');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');

// Public API reference for integrators (rendered from the OpenAPI description).
Route::get('/developers/api', fn () => view('developers.api', [
    'base' => url('/api/v1'),
    'endpoints' => OpenApi::endpoints(),
    'scopes' => ApiScope::cases(),
    'events' => WebhookEvent::cases(),
]))->name('api.docs');

Route::get('/locale/{locale}', SwitchLocaleController::class)
    ->whereIn('locale', array_keys(config('locales.supported')))
    ->name('locale.switch');

Route::post('/request-access', SubmitLeadController::class)
    ->middleware('throttle:leads')
    ->name('leads.store');

Route::get('/invitation/{token}', [InvitationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:30,1')
    ->name('invitation.show');

Route::post('/invitation/{token}', [InvitationController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:10,1')
    ->name('invitation.accept');

Route::get('/invoices/{invoice}/pdf', DownloadInvoiceController::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->name('invoices.pdf');

Route::post('/impersonation/leave', LeaveImpersonationController::class)
    ->middleware('auth')
    ->name('impersonation.leave');

/*
| Shared branch screens (kitchen, cashier, pickup, TV): pair once with a
| one-time code, then stay signed in by a device cookie.
*/
Route::get('/pair', [DeviceScreenController::class, 'pairForm'])->name('screen.pair');
Route::post('/pair', [DeviceScreenController::class, 'pair'])->middleware('throttle:10,1')->name('screen.pair.store');

Route::middleware(AuthenticateDevice::class)->group(function (): void {
    Route::get('/screen', [DeviceScreenController::class, 'show'])->name('screen.show');
    Route::get('/screen/heartbeat', [DeviceScreenController::class, 'heartbeat'])->middleware('throttle:20,1')->name('screen.heartbeat');
});
Route::post('/screen/disconnect', [DeviceScreenController::class, 'disconnect'])->name('screen.disconnect');

Route::get('/qr/{restaurant:slug}/{format}', DownloadQrController::class)
    ->whereIn('format', ['png', 'svg', 'pdf'])
    ->middleware(['auth', 'throttle:60,1'])
    ->name('qr.download');

/*
| Fake payment provider's 3-D Secure page (until a real provider is set up).
*/
Route::get('/pay/{reference}', [FakePaymentController::class, 'show'])->where('reference', 'FAKE-[A-Z0-9]{10}')->name('payments.fake');
Route::post('/pay/{reference}', [FakePaymentController::class, 'decide'])->where('reference', 'FAKE-[A-Z0-9]{10}')->middleware('throttle:20,1')->name('payments.fake.decide');

/*
| Operations screens for signed-in staff (the same screens run on paired devices at /screen).
*/
Route::middleware(['auth', 'throttle:240,1'])->prefix('ops/{restaurant:slug}')->group(function (): void {
    Route::get('/', [OpsController::class, 'home'])->name('ops.home');
    Route::get('/overview', [OpsController::class, 'overview'])->name('ops.overview');
    Route::get('/go/{screen}', [OpsController::class, 'go'])->where('screen', 'kitchen|live|pickup|tv|pager|ready|stamps')->name('ops.go');
    Route::get('/{branch}/{screen}', [OpsController::class, 'screen'])->where(['branch' => '[a-z0-9][a-z0-9-]*', 'screen' => 'kitchen|live|pickup|tv|pager|ready|stamps'])->name('ops.screen');
});

// 80 mm tickets and receipts for the browser's print dialog (paired screens and staff).
Route::get('/print/{code}/{kind}', PrintController::class)->where(['code' => '[2-9A-HJ-NP-Za-hj-np-z]{6}', 'kind' => 'kitchen|receipt'])->middleware('throttle:60,1')->name('print.ticket');

// Realtime sign-off for paired screens and guests (panel staff use /broadcasting/auth).
Route::post('/realtime/auth', RealtimeAuthController::class)->middleware('throttle:120,1')->name('realtime.auth');

// SADAD (Qatar): hand-off page, the guest's return, and the server webhook.
Route::get('/pay/sadad/{reference}', [SadadController::class, 'checkout'])->where('reference', 'SDD[A-Z0-9]{13}')->middleware('throttle:30,1')->name('payments.sadad.checkout');
Route::post('/payments/sadad/callback', [SadadController::class, 'callback'])->middleware('throttle:60,1')->name('payments.sadad.callback');
Route::post('/payments/sadad/webhook', [SadadController::class, 'webhook'])->middleware('throttle:240,1')->name('payments.sadad.webhook');

// Messaging delivery reports (Meta WhatsApp signs with our app secret; SMSala calls our signed URL).
Route::get('/webhooks/whatsapp', [MessagingWebhookController::class, 'verifyWhatsApp'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [MessagingWebhookController::class, 'whatsapp'])->middleware('throttle:600,1')->name('webhooks.whatsapp');
Route::match(['get', 'post'], '/webhooks/smsala/{delivery}', [MessagingWebhookController::class, 'smsala'])->whereNumber('delivery')->middleware(['signed', 'throttle:600,1'])->name('webhooks.smsala');

// Web push subscriptions for this browser (signed-in staff, or a guest of ?restaurant=).
Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])->middleware('throttle:30,1')->name('push.subscribe');
Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:30,1')->name('push.unsubscribe');

// Short tracking links printed on receipts and shared by guests: /o/7KQ2MX
Route::get('/o/{code}', ShortLinkController::class)->where('code', '[2-9A-HJ-NP-Za-hj-np-z]{6}')->middleware('throttle:60,1')->name('short.order');
Route::get('/o/{code}/view', [ShortLinkController::class, 'staff'])->where('code', '[2-9A-HJ-NP-Za-hj-np-z]{6}')->middleware(['signed', 'throttle:60,1'])->name('short.order.staff');

/*
| The guest app. Keep these last: they match any slug no platform route
| claimed (platform paths are reserved slugs), and the fixed paths under a
| restaurant come before the branch wildcard (they are reserved branch codes).
*/
$slug = CreateRestaurant::publicSlugPattern();
$missing = fn () => response()->view('public.restaurant-missing', [], 404);

Route::middleware(ResolveGuestRestaurant::class)->group(function () use ($slug, $missing): void {
    Route::livewire('/{restaurant:slug}', Customer\Home::class)->where('restaurant', $slug)->missing($missing)->name('public.restaurant');
    Route::get('/{restaurant:slug}/manifest.webmanifest', ManifestController::class)->where('restaurant', $slug)->missing($missing)->name('customer.manifest');
    Route::livewire('/{restaurant:slug}/sign-in', Customer\SignInPage::class)->where('restaurant', $slug)->missing($missing)->name('customer.sign-in');
    Route::livewire('/{restaurant:slug}/orders', Customer\Orders::class)->where('restaurant', $slug)->missing($missing)->name('customer.orders');
    Route::livewire('/{restaurant:slug}/orders/{order:short_code}', Customer\OrderStatus::class)->where('restaurant', $slug)->missing($missing)->middleware('throttle:120,1')->name('customer.order');
    Route::get('/{restaurant:slug}/orders/{order:short_code}/receipt', ReceiptController::class)->where('restaurant', $slug)->missing($missing)->name('customer.receipt');
    Route::livewire('/{restaurant:slug}/p/{token}', Customer\Pager::class)->where(['restaurant' => $slug, 'token' => '[A-Za-z0-9]{32}'])->missing($missing)->middleware('throttle:120,1')->name('customer.pager');
    Route::livewire('/{restaurant:slug}/rewards', Customer\Rewards::class)->where('restaurant', $slug)->missing($missing)->name('customer.rewards');
    Route::livewire('/{restaurant:slug}/account', Customer\Account::class)->where('restaurant', $slug)->missing($missing)->name('customer.account');
    Route::livewire('/{restaurant:slug}/unsubscribe/{token}', Customer\Unsubscribe::class)->where(['restaurant' => $slug, 'token' => '[0-9a-z]{1,12}\.[A-Za-z0-9_-]{22}'])->missing($missing)->middleware('throttle:30,1')->name('customer.unsubscribe');
    Route::livewire('/{restaurant:slug}/{branch}/checkout', Customer\Checkout::class)->where(['restaurant' => $slug, 'branch' => '[a-z0-9][a-z0-9-]*'])->missing($missing)->name('customer.checkout');
    Route::livewire('/{restaurant:slug}/{branch}', Customer\BranchMenu::class)->where(['restaurant' => $slug, 'branch' => '[a-z0-9][a-z0-9-]*'])->missing($missing)->name('public.branch');
});
