<?php

use App\Domain\Campaigns\Actions\RunCampaign;
use App\Domain\Campaigns\Enums\CampaignChannel;
use App\Domain\Campaigns\Enums\CampaignStatus;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignMessage;
use App\Domain\Crm\Consent;
use App\Domain\Crm\Enums\Segment;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Crm\UnsubscribeLink;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\WalletReward;
use App\Domain\Messaging\Models\MessageDelivery;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\App\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\App\Resources\Campaigns\Pages\ViewCampaign;
use App\Livewire\Customer\Unsubscribe;
use App\Models\User;
use App\Support\Bidi;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config([
        'messaging.log_when_unconfigured' => false,
        'messaging.whatsapp.phone_number_id' => '1234567890',
        'messaging.whatsapp.access_token' => 'wa-token',
        'messaging.whatsapp.app_secret' => 'wa-app-secret',
        'messaging.smsala.api_token' => 'sms-token',
        'messaging.smsala.sender_id' => 'MASAR',
    ]);
    Http::fake(['graph.facebook.com/*' => Http::sequence()->whenEmpty(Http::response(['messages' => [['id' => 'wamid.X']]]))]);
    app()->setLocale('en');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Qatar'));
});

/** @return array{Restaurant, User} */
function campaignWorkspace(?string $position = null, string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::DriveThru, Service::Rewards]);
    $user = $position === null ? ownerOf($restaurant) : employeeAt($restaurant, $position, ['all_branches' => true]);
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return [$restaurant->fresh(), $user];
}

function fan(Restaurant $restaurant, string $phone, string $locale = 'en', bool $agrees = true, ?string $name = 'Noura'): Customer
{
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => $phone, 'name' => $name, 'locale' => $locale]);

    if ($agrees) {
        app(Consent::class)->give($guest, 'checkout');
    }

    return $guest->fresh();
}

/** @param  array<string, mixed>  $attributes */
function offer(Restaurant $restaurant, array $attributes = []): Campaign
{
    return Campaign::withoutTenancy()->create(array_merge([
        'restaurant_id' => $restaurant->id,
        'name' => 'Weekend treat',
        'channel' => CampaignChannel::WhatsApp,
        'message' => ['en' => 'Free fries with any burger this weekend', 'ar' => 'بطاطس مجانية مع أي برجر هذا الأسبوع'],
    ], $attributes));
}

/** Schedules for now, starts and runs one sending slice, as the minute job does. */
function sendNow(Campaign $campaign, User $by): Campaign
{
    $run = app(RunCampaign::class);
    $run->schedule($campaign, null, $by);
    $run->start($campaign->fresh());
    $run->send($campaign->fresh());

    return $campaign->fresh();
}

function picked(Customer $guest, int $total, CarbonImmutable $at, array $source = []): Order
{
    $order = pastOrder($guest, $total, $at, attributes: $source === [] ? [] : ['source' => $source]);
    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));

    return $order;
}

it('lets the owner write a campaign, send it, and each guest gets it in their language with a stop link and the gift', function () {
    [$restaurant, $owner] = campaignWorkspace();
    $reward = Reward::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Free fries', 'ar' => 'بطاطس مجانية'], 'type' => RewardType::FreeItem]);
    $noura = fan($restaurant, '+97455551234', 'ar');
    $sam = fan($restaurant, '+97455551235', 'en', name: 'Sam');
    fan($restaurant, '+97455551236', agrees: false);

    Livewire::test(CreateCampaign::class)
        ->fillForm([
            'name' => 'Weekend treat',
            'channel' => CampaignChannel::WhatsApp->value,
            'message' => ['en' => 'Free fries with any burger', 'ar' => 'بطاطس مجانية مع أي برجر'],
            'reward_id' => $reward->id,
            'when' => 'now',
        ])
        ->assertSee(trans_choice('campaigns.admin.audience.count', 2, ['count' => 2]))
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(ViewCampaign::getUrl(['record' => Campaign::withoutTenancy()->sole()], tenant: $restaurant));

    $campaign = Campaign::withoutTenancy()->sole();
    expect(UnsubscribeLink::resolve($restaurant, basename(UnsubscribeLink::sampleUrl($restaurant))))->toBeNull();
    expect($campaign->status)->toBe(CampaignStatus::Draft)->and($campaign->created_by)->toBe($owner->id);

    Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
        ->assertSee(__('campaigns.admin.results.not_started'))
        ->callAction('send')
        ->assertNotified(__('campaigns.admin.actions.started'));

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->recipients_count)->toBe(2)
        ->and($campaign->sent_count)->toBe(2)
        ->and(WalletReward::withoutTenancy()->where('source', 'campaign')->pluck('customer_id')->sort()->values()->all())->toBe([$noura->id, $sam->id])
        ->and(MessageDelivery::withoutTenancy()->where('purpose', 'campaign')->count())->toBe(2);

    Http::assertSent(fn (Request $request) => $request['to'] === '97455551234'
        && $request['template']['language']['code'] === 'ar'
        && $request['template']['components'][0]['parameters'][1]['text'] === 'بطاطس مجانية مع أي برجر'
        && str_contains($request['template']['components'][0]['parameters'][2]['text'], 'campaign='.$campaign->link_code)
        && $request['template']['components'][0]['parameters'][3]['text'] === UnsubscribeLink::url($noura, $restaurant));
    Http::assertSent(fn (Request $request) => $request['to'] === '97455551235'
        && $request['template']['components'][0]['parameters'][1]['text'] === 'Free fries with any burger');

    expect(Activity::query()->where('event', 'campaign.scheduled')->sole()->causer_id)->toBe($owner->id);
});

it('checks each guest at the moment of sending: a guest who opted out after scheduling is skipped', function () {
    [$restaurant, $owner] = campaignWorkspace();
    $guest = fan($restaurant, '+97455551234');
    $campaign = offer($restaurant);
    $run = app(RunCampaign::class);
    $run->schedule($campaign, null, $owner);
    $run->start($campaign->fresh());

    app(Consent::class)->withdraw($guest->fresh(), 'account');
    $run->send($campaign->fresh());

    expect(CampaignMessage::withoutTenancy()->sole())->status->toBe('skipped')->skip_reason->toBe('no_consent');
    Http::assertNothingSent();
});

it('holds the frequency cap across campaigns: one a day, three a week', function () {
    [$restaurant, $owner] = campaignWorkspace();
    fan($restaurant, '+97455551234');

    expect(sendNow(offer($restaurant), $owner)->sent_count)->toBe(1)
        ->and(sendNow(offer($restaurant, ['name' => 'Second']), $owner)->skipped_count)->toBe(1);

    $this->travel(25)->hours();
    expect(sendNow(offer($restaurant, ['name' => 'Third']), $owner)->sent_count)->toBe(1);
    $this->travel(25)->hours();
    expect(sendNow(offer($restaurant, ['name' => 'Fourth']), $owner)->sent_count)->toBe(1);
    $this->travel(25)->hours();
    expect(sendNow(offer($restaurant, ['name' => 'Fifth']), $owner)->skipped_count)->toBe(1)
        ->and(CampaignMessage::withoutTenancy()->where('skip_reason', 'frequency_cap')->count())->toBe(2);
});

it('waits for quiet hours to end before sending', function () {
    [$restaurant, $owner] = campaignWorkspace();
    fan($restaurant, '+97455551234');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 23:30', 'Asia/Qatar'));

    $campaign = sendNow(offer($restaurant), $owner);

    expect($campaign->status)->toBe(CampaignStatus::Sending)
        ->and($campaign->sent_count)->toBe(0)
        ->and($campaign->next_attempt_at->timezone('Asia/Qatar')->format('Y-m-d H:i'))->toBe('2026-10-21 09:00');
    Http::assertNothingSent();

    Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
        ->assertSee(__('campaigns.admin.results.waiting_quiet', ['time' => (string) Bidi::ltr('09:00')]));

    $this->travelTo(CarbonImmutable::parse('2026-10-21 09:01', 'Asia/Qatar'));
    $this->artisan('campaigns:send')->assertSuccessful();

    expect($campaign->fresh()->sent_count)->toBe(1)->and($campaign->fresh()->status)->toBe(CampaignStatus::Sent);
});

it('sends texts by SMS in the guest’s language when SMS is chosen', function () {
    [$restaurant, $owner] = campaignWorkspace();
    Http::fake(['api2.smsala.com/*' => Http::response([['Status' => 'Success', 'OperationCode' => 0, 'MessageId' => 9]])]);
    fan($restaurant, '+97455551234', 'ar');

    sendNow(offer($restaurant, ['channel' => CampaignChannel::Sms]), $owner);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/SendSmsV2')
        && str_contains($request['messageText'], 'بطاطس مجانية')
        && str_contains($request['messageText'], 'لإيقاف العروض'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'graph.facebook.com'));
});

it('stops offers from the link without signing in, only after a tap, and audits it', function () {
    [$restaurant] = campaignWorkspace();
    $guest = fan($restaurant, '+97455551234');
    $token = UnsubscribeLink::token($guest);
    auth()->logout();

    $this->get(route('customer.unsubscribe', [$restaurant, $token]))->assertOk()
        ->assertSee(__('campaigns.unsubscribe.title', ['restaurant' => $restaurant->name]));
    expect($guest->fresh()->acceptsOffers())->toBeTrue();

    Livewire::test(Unsubscribe::class, ['restaurant' => $restaurant, 'token' => $token])
        ->call('stop')
        ->assertSee(__('campaigns.unsubscribe.done_title'));

    expect($guest->fresh()->acceptsOffers())->toBeFalse()
        ->and(Activity::query()->where('event', 'crm.consent_withdrawn')->sole()->properties['source'])->toBe('unsubscribe_link');

    Livewire::test(Unsubscribe::class, ['restaurant' => $restaurant, 'token' => $token])->assertSee(__('campaigns.unsubscribe.already_title'));
});

it('refuses a forged or moved unsubscribe link', function () {
    [$restaurant] = campaignWorkspace();
    $guest = fan($restaurant, '+97455551234');
    $other = restaurant(slug: 'elsewhere');
    $token = UnsubscribeLink::token($guest);
    $forged = substr($token, 0, -1).(str_ends_with($token, 'A') ? 'B' : 'A');

    Livewire::test(Unsubscribe::class, ['restaurant' => $restaurant, 'token' => $forged])->assertSee(__('campaigns.unsubscribe.invalid_title'))->call('stop');
    Livewire::test(Unsubscribe::class, ['restaurant' => $other, 'token' => $token])->assertSee(__('campaigns.unsubscribe.invalid_title'))->call('stop');

    expect($guest->fresh()->acceptsOffers())->toBeTrue();
    $this->get('/masar/unsubscribe/not-a-token')->assertNotFound();
});

it('treats a STOP or إيقاف reply on WhatsApp as unsubscribing from the restaurant that sent the offer', function () {
    [$restaurant, $owner] = campaignWorkspace();
    $guest = fan($restaurant, '+97455551234');
    $elsewhere = restaurant(slug: 'elsewhere');
    $there = fan($elsewhere, '+97455551234');
    sendNow(offer($restaurant), $owner);

    $reply = fn (string $text) => json_encode(['entry' => [['changes' => [['value' => ['messages' => [['from' => '97455551234', 'type' => 'text', 'text' => ['body' => $text]]]]]]]]]);

    $hello = $reply('Hello');
    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $hello, 'wa-app-secret')], $hello)->assertOk();
    expect($guest->fresh()->acceptsOffers())->toBeTrue();

    $stop = $reply(' إيقاف ');
    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $stop)->assertForbidden();
    expect($guest->fresh()->acceptsOffers())->toBeTrue();

    $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $stop, 'wa-app-secret')], $stop)->assertOk();

    expect($guest->fresh()->acceptsOffers())->toBeFalse()
        ->and($there->fresh()->acceptsOffers())->toBeTrue()
        ->and(Activity::query()->where('event', 'crm.consent_withdrawn')->sole()->properties['source'])->toBe('whatsapp_stop');
});

it('credits a picked-up order to the campaign: by its link, or by time within 7 days, once', function () {
    [$restaurant, $owner] = campaignWorkspace();
    $noura = fan($restaurant, '+97455551234');
    $sam = fan($restaurant, '+97455551235', name: 'Sam');
    $late = fan($restaurant, '+97455551236', name: 'Late');
    $campaign = sendNow(offer($restaurant), $owner);

    $first = picked($noura, 4500, CarbonImmutable::now()->addDays(2));
    picked($noura, 3000, CarbonImmutable::now()->addDays(3));
    event(new OrderStatusChanged($first, OrderStatus::Ready, OrderStatus::PickedUp));
    picked($sam, 2000, CarbonImmutable::now()->addDays(9), ['campaign' => $campaign->link_code]);
    picked($late, 9900, CarbonImmutable::now()->addDays(8));

    $campaign->refresh();
    expect($campaign->orders_count)->toBe(2)
        ->and($campaign->revenue_minor)->toBe(6500)
        ->and($campaign->conversionPercent())->toBe(66.7)
        ->and(CampaignMessage::withoutTenancy()->where('customer_id', $noura->id)->value('order_id'))->toBe($first->id);

    Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
        ->assertSee(__('campaigns.admin.results.orders'))
        ->assertSee('66.7%');
});

it('sends a test to the owner’s phone without touching guests, and limits tests', function () {
    [$restaurant] = campaignWorkspace();
    $guest = fan($restaurant, '+97455551234');
    $campaign = offer($restaurant);

    Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
        ->callAction('test', data: ['phone' => '5555 9999', 'locale' => 'ar'])
        ->assertNotified();

    Http::assertSent(fn (Request $request) => $request['to'] === '97455559999' && $request['template']['language']['code'] === 'ar');
    expect(CampaignMessage::withoutTenancy()->count())->toBe(0)
        ->and(WalletReward::withoutTenancy()->count())->toBe(0)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Draft)
        ->and($guest->fresh()->acceptsOffers())->toBeTrue();

    $page = Livewire::test(ViewCampaign::class, ['record' => $campaign->id]);
    foreach (range(1, 4) as $_) {
        $page->callAction('test', data: ['phone' => '55559999', 'locale' => 'en']);
    }
    $page->callAction('test', data: ['phone' => '55559999', 'locale' => 'en'])->assertNotified(__('campaigns.errors.test_limit'));
});

it('cancels a sending campaign: what is left is never sent', function () {
    [$restaurant, $owner] = campaignWorkspace();
    fan($restaurant, '+97455551234');
    fan($restaurant, '+97455551235');
    $campaign = offer($restaurant);
    $run = app(RunCampaign::class);
    $run->schedule($campaign, null, $owner);
    $run->start($campaign->fresh());

    Livewire::test(ViewCampaign::class, ['record' => $campaign->id])->callAction('cancel');
    $run->send($campaign->fresh());

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Cancelled)
        ->and(CampaignMessage::withoutTenancy()->where('skip_reason', 'cancelled')->count())->toBe(2);
    Http::assertNothingSent();
});

it('targets only the chosen groups', function () {
    [$restaurant, $owner] = campaignWorkspace();
    $vip = fan($restaurant, '+97455551234');
    fan($restaurant, '+97455551235');
    CustomerSegment::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'customer_id' => $vip->id, 'segment' => Segment::Vip, 'computed_at' => now()]);

    $campaign = sendNow(offer($restaurant, ['segments' => [Segment::Vip->value]]), $owner);

    expect($campaign->recipients_count)->toBe(1)
        ->and(CampaignMessage::withoutTenancy()->sole()->customer_id)->toBe($vip->id);
});

it('keeps campaigns to their restaurant, their permission and the Rewards service', function () {
    $other = restaurant(slug: 'elsewhere', services: [Service::DriveThru, Service::Rewards]);
    $theirs = offer($other);

    [$restaurant] = campaignWorkspace('cashier');
    $this->get(ListCampaigns::getUrl(tenant: $restaurant))->assertForbidden();

    $this->actingAs(ownerOf($restaurant));
    $this->get(ListCampaigns::getUrl(tenant: $restaurant))->assertOk()->assertDontSee('Weekend treat');
    $this->get(ViewCampaign::getUrl(['record' => $theirs], tenant: $restaurant))->assertNotFound();

    // Without the Rewards service (it carries campaigns) the page is closed.
    RestaurantService::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('service', Service::Rewards->value)->delete();
    $this->get(ListCampaigns::getUrl(tenant: $restaurant))->assertForbidden();
});
