<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\QrFunnel;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Loyalty\Badges\BadgeSettings;
use App\Domain\Loyalty\Badges\CustomerBadge;
use App\Domain\Loyalty\Birthdays\BirthdaySettings;
use App\Domain\Menu\Models\Product;
use App\Domain\Referrals\Models\Referral;
use App\Domain\Referrals\ReferralSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Support\Models\SupportTicket;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoFeedbackSeeder;
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

it('adds ratings, support tickets, birthdays and badges on top, once', function () {
    $restaurant = restaurant(slug: 'masar', services: [Service::DriveThru, Service::Rewards]);
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => true, 'campaigns' => true]])->save();
    Branch::factory()->create(['restaurant_id' => $restaurant->id]);
    Product::factory()->count(6)->create(['restaurant_id' => $restaurant->id, 'price_minor' => 1800, 'is_available' => true]);
    ownerOf($restaurant);

    app(DemoGrowthSeeder::class)->run($restaurant);
    app(DemoCrmSeeder::class)->run($restaurant->fresh());
    app(DemoFeedbackSeeder::class)->run($restaurant->fresh());
    $ratings = OrderRating::withoutTenancy()->count();
    app(DemoFeedbackSeeder::class)->run($restaurant->fresh());

    $tickets = SupportTicket::withoutTenancy()->orderBy('number')->get();

    expect($ratings)->toBeGreaterThan(10)->toBeLessThanOrEqual(40)
        ->and(OrderRating::withoutTenancy()->count())->toBe($ratings)
        ->and(OrderRating::withoutTenancy()->where('stars', '<=', 2)->count())->toBeGreaterThan(0)
        ->and($tickets->pluck('status')->all())->toBe(['open', 'answered', 'resolved'])
        ->and($tickets->last()->outcome)->toBe('reward')
        ->and(BirthdaySettings::for($restaurant->fresh())->isLive())->toBeTrue()
        ->and(Customer::withoutTenancy()->whereNotNull('birthday')->count())->toBe(2)
        ->and(BadgeSettings::for($restaurant->fresh())->isLive())->toBeTrue()
        ->and(CustomerBadge::withoutTenancy()->where('badge', 'first_order')->count())->toBe(13);
});
