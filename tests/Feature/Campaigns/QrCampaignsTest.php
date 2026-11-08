<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\QrCodes;
use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\Models\QrScan;
use App\Domain\Campaigns\QrFunnel;
use App\Domain\Customers\Models\Customer;
use App\Domain\Services\Enums\Service;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Resources\QrCampaigns\Pages\CreateQrCampaign;
use App\Filament\App\Resources\QrCampaigns\Pages\ListQrCampaigns;
use App\Filament\App\Resources\QrCampaigns\Pages\ViewQrCampaign;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;

const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

beforeEach(function () {
    app()->setLocale('en');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Qatar'));
});

/** @return array{Restaurant, Branch} */
function qrShop(string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug, services: [Service::DriveThru, Service::Rewards]);
    $branch = Branch::factory()->create(['restaurant_id' => $restaurant->id, 'code' => 'lusail']);

    return [$restaurant->fresh(), $branch];
}

function qrCode(Restaurant $restaurant, array $attributes = []): QrCampaign
{
    return QrCampaign::withoutTenancy()->create(array_merge([
        'restaurant_id' => $restaurant->id,
        'name' => 'Car-window sticker',
        'placement' => 'Car-window sticker',
    ], $attributes));
}

function qrWorkspace(Restaurant $restaurant, ?string $position = null): void
{
    test()->actingAs($position === null ? ownerOf($restaurant) : employeeAt($restaurant, $position, ['all_branches' => true]));
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);
}

it('counts a scan and opens the branch menu carrying where the guest came from', function () {
    [$restaurant, $branch] = qrShop();
    $code = qrCode($restaurant, ['branch_id' => $branch->id, 'lane' => '2', 'promo' => 'WELCOME10']);

    $this->withHeader('User-Agent', PHONE)->get('/q/'.$code->code)
        ->assertRedirect(route('public.branch', [$restaurant, 'lusail']).'?source=qr&campaign='.$code->code.'&channel=Car-window+sticker&lane=2&promo=WELCOME10');

    expect(QrScan::withoutTenancy()->sole())
        ->qr_campaign_id->toBe($code->id)
        ->is_bot->toBeFalse()
        ->visited_at->toBeNull()
        ->and(strlen(QrScan::withoutTenancy()->sole()->device_hash))->toBe(64);
});

it('counts the same phone once in 30 minutes, and again after', function () {
    [$restaurant] = qrShop();
    $code = qrCode($restaurant);

    $this->withHeader('User-Agent', PHONE)->get('/q/'.$code->code);
    $this->travel(10)->minutes();
    $this->withHeader('User-Agent', PHONE)->get('/q/'.$code->code);
    expect(QrScan::withoutTenancy()->count())->toBe(1);

    $this->travel(31)->minutes();
    $this->withHeader('User-Agent', PHONE)->get('/q/'.$code->code);
    expect(QrScan::withoutTenancy()->count())->toBe(2);
});

it('keeps link previews and crawlers out of the numbers', function () {
    [$restaurant] = qrShop();
    $code = qrCode($restaurant);

    foreach (['WhatsApp/2.23.20.0', 'facebookexternalhit/1.1', 'Googlebot/2.1', ''] as $agent) {
        $this->withHeader('User-Agent', $agent)->get('/q/'.$code->code)->assertRedirect();
    }

    expect(QrScan::withoutTenancy()->where('is_bot', true)->count())->toBe(4)
        ->and(QrFunnel::for($code, 'Asia/Qatar')['scans'])->toBe(0);
});

it('follows a scan to the menu and to an order, and shows it in the funnel', function () {
    [$restaurant, $branch] = qrShop();
    $code = qrCode($restaurant, ['branch_id' => $branch->id]);

    $target = $this->withHeader('User-Agent', PHONE)->get('/q/'.$code->code)->headers->get('Location');
    $this->withHeader('User-Agent', PHONE)->get($target)->assertOk();

    expect(QrScan::withoutTenancy()->sole()->visited_at)->not->toBeNull();

    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455551234']);
    pastOrder($guest, 4200, now(), attributes: ['source' => ['source' => 'qr', 'campaign' => $code->code]]);
    pastOrder($guest, 9900, now(), attributes: ['source' => ['source' => 'qr', 'campaign' => 'OTHER1']]);

    $funnel = QrFunnel::for($code, 'Asia/Qatar');
    expect($funnel)->toMatchArray(['scans' => 1, 'visits' => 1, 'orders' => 1, 'revenue_minor' => 4200, 'conversion' => 100.0])
        ->and(collect($funnel['days'])->last())->toBe(['date' => '2026-10-20', 'scans' => 1, 'orders' => 1])
        ->and($funnel['days'])->toHaveCount(30);

    qrWorkspace($restaurant);
    Livewire::test(ViewQrCampaign::class, ['record' => $code->id])
        ->assertSee(__('qr.funnel.title'))
        ->assertSee('42.00')
        ->assertSee('100%');
});

it('opens the restaurant page without counting when a code is off or out of its dates', function (array $attributes) {
    [$restaurant] = qrShop();
    $code = qrCode($restaurant, $attributes);

    $this->withHeader('User-Agent', PHONE)->get('/q/'.$code->code)->assertRedirect(route('public.restaurant', $restaurant));

    expect(QrScan::withoutTenancy()->count())->toBe(0);
})->with([
    'switched off' => [['is_active' => false]],
    'not started' => [['starts_at' => '2026-11-01 00:00:00']],
    'ended' => [['ends_at' => '2026-10-01 00:00:00']],
]);

it('answers 404 for a code that does not exist', function () {
    $this->get('/q/ZZZZZZ')->assertNotFound();
});

it('lets the owner create a code, then download the image, the SVG and every poster size', function () {
    [$restaurant, $branch] = qrShop();
    qrWorkspace($restaurant);

    Livewire::test(CreateQrCampaign::class)
        ->fillForm(['name' => 'ملصق نافذة السيارة', 'placement' => 'Car-window sticker', 'branch_id' => $branch->id, 'lane' => '1', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $code = QrCampaign::withoutTenancy()->sole();
    expect($code->code)->toMatch('/^[2-9A-HJ-NP-Z]{6}$/')->and($code->branch_id)->toBe($branch->id);

    $page = Livewire::test(ViewQrCampaign::class, ['record' => $code->id])->assertSee($code->code);
    $file = 'masar-'.Str::slug($code->name).'-qr'; // Arabic names are transliterated

    foreach (['a5', 'a4', 'sticker'] as $format) {
        $page->callAction('download_'.$format)->assertFileDownloaded($file.'-'.$format.'.pdf');
    }
    $page->callAction('download_png')->assertFileDownloaded($file.'.png');
    $page->callAction('download_svg')->assertFileDownloaded($file.'.svg');
});

it('renders bilingual poster PDFs in every size', function (string $format) {
    [$restaurant, $branch] = qrShop();
    $code = qrCode($restaurant, ['branch_id' => $branch->id]);

    $pdf = app(QrCodes::class)->poster($restaurant, $branch, $code->url(), 'order', $format);

    // One page each: nothing spills onto a second sheet or sticker.
    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(1);
})->with(['a5', 'a4', 'sticker']);

it('switches a code off and on from its page', function () {
    [$restaurant] = qrShop();
    $code = qrCode($restaurant);
    qrWorkspace($restaurant);

    Livewire::test(ViewQrCampaign::class, ['record' => $code->id])->callAction('toggle');
    expect($code->fresh()->is_active)->toBeFalse();
});

it('keeps codes to their restaurant and to staff who manage campaigns', function () {
    [$other] = qrShop('elsewhere');
    $theirs = qrCode($other);
    [$restaurant] = qrShop();

    qrWorkspace($restaurant, 'cashier');
    $this->get(ListQrCampaigns::getUrl(tenant: $restaurant))->assertForbidden();

    $this->actingAs(ownerOf($restaurant));
    $this->get(ListQrCampaigns::getUrl(tenant: $restaurant))->assertOk()->assertDontSee($theirs->code);
    $this->get(ViewQrCampaign::getUrl(['record' => $theirs], tenant: $restaurant))->assertNotFound();
});
