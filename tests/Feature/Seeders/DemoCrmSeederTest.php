<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\QrFunnel;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Menu\Models\Product;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\ReferralSettings;
use App\Domain\Services\Enums\Service;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoGrowthSeeder;

it('fills the growth screens with demo guests, a campaign, a QR sticker and invites, once', function () {
    $restaurant = restaurant(slug: 'masar', services: [Service::DriveThru, Service::Rewards]);
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => true, 'campaigns' => true]])->save();
    Branch::factory()->create(['restaurant_id' => $restaurant->id]);
    Product::factory()->count(6)->create(['restaurant_id' => $restaurant->id, 'price_minor' => 1800, 'is_available' => true]);

    app(DemoGrowthSeeder::class)->run($restaurant);
    app(DemoCrmSeeder::class)->run($restaurant->fresh());
    app(DemoCrmSeeder::class)->run($restaurant->fresh());

    $campaign = Campaign::withoutTenancy()->sole();
    $code = QrCampaign::withoutTenancy()->sole();

    expect(Customer::withoutTenancy()->count())->toBe(14)
        ->and(CustomerSegment::withoutTenancy()->pluck('segment')->countBy()->keys()->all())->toContain('regular', 'new', 'at_risk', 'inactive')
        ->and($campaign->status->value)->toBe('sent')
        ->and($campaign->orders_count)->toBe(3)
        ->and(QrFunnel::for($code, $restaurant->timezone)['orders'])->toBe(7)
        ->and(Referral::withoutTenancy()->pluck('status')->sort()->values()->all())->toBe(['pending', 'rewarded'])
        ->and(ReferralSettings::for($restaurant->fresh())->isLive())->toBeTrue();
});
