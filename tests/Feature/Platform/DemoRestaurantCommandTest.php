<?php

use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Customers\Models\Customer;
use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Loyalty\Badges\BadgeSettings;
use App\Domain\Loyalty\Birthdays\BirthdaySettings;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Ordering\Models\Order;
use App\Domain\Referrals\ReferralSettings;
use App\Domain\Services\Enums\Service;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an owner with a full demo restaurant to try every feature', function () {
    $this->artisan('demo:restaurant', [
        '--name' => 'Rabii Souai',
        '--email' => 'RabiiSouai@Gmail.com',
        '--phone' => '66625167',
        '--password' => 'Demo-Pass-2026',
        '--force' => true,
    ])->assertSuccessful();

    $owner = User::query()->where('email', 'rabiisouai@gmail.com')->sole();
    $restaurant = Restaurant::query()->where('slug', 'rabii')->sole();

    expect(Hash::check('Demo-Pass-2026', $owner->password))->toBeTrue()
        ->and($owner->phone)->toBe('+97466625167')
        ->and($owner->employmentAt($restaurant)?->is_owner)->toBeTrue()
        ->and(collect(Service::cases())->every(fn (Service $s): bool => $restaurant->hasService($s)))->toBeTrue()
        ->and($restaurant->hasFeature(PlanFeature::Campaigns))->toBeTrue()
        ->and($restaurant->branches()->count())->toBe(2);

    // The owner's own phone is a guest too, with the reports and a fresh order to rate.
    $me = Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('phone', '+97466625167')->sole();
    $latest = Order::withoutTenancy()->where('customer_id', $me->id)->latest('picked_up_at')->first();

    $card = StampCard::withoutTenancy()->where('customer_id', $me->id)->sole();
    expect($card->stamps)->toBe(6)
        ->and(StampProgram::withoutTenancy()->find($card->stamp_program_id)->stamps_needed)->toBe(9);

    expect($me->acceptsOffers())->toBeTrue()
        ->and(SupportTicket::withoutTenancy()->where('restaurant_id', $restaurant->id)->pluck('customer_id')->unique()->all())->toBe([$me->id])
        ->and(OrderRating::withoutTenancy()->where('order_id', $latest->id)->exists())->toBeFalse()
        ->and($latest->picked_up_at->gt(now()->subHours(2)))->toBeTrue();

    // Nothing can reach the made-up demo numbers: only the owner's guest accepts offers, birthday messages are off.
    expect(Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('id', '!=', $me->id)->whereNotNull('offers_consent_at')->count())->toBe(0)
        ->and(BirthdaySettings::for($restaurant)->sendMessage)->toBeFalse()
        ->and(BirthdaySettings::for($restaurant)->isLive())->toBeTrue()
        ->and(BadgeSettings::for($restaurant)->isLive())->toBeTrue()
        ->and(ReferralSettings::for($restaurant)->isLive())->toBeTrue();
});

it('refuses a slug that is taken and passwords that do not match', function () {
    restaurant(slug: 'rabii');

    $this->artisan('demo:restaurant', ['--name' => 'Rabii Souai', '--email' => 'a@b.test', '--phone' => '66625167', '--password' => 'Demo-Pass-2026', '--force' => true])
        ->assertFailed();

    $this->artisan('demo:restaurant', ['--name' => 'Rabii Souai', '--email' => 'a@b.test', '--phone' => '66625167', '--slug' => 'rabii-2', '--force' => true])
        ->expectsQuestion('Password', 'Demo-Pass-2026')
        ->expectsQuestion('Password again', 'Other-Pass-2026')
        ->assertFailed();

    expect(User::query()->where('email', 'a@b.test')->exists())->toBeFalse();
});
