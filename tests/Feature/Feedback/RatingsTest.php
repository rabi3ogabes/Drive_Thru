<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerSession;
use App\Domain\Feedback\Actions\RateOrder;
use App\Domain\Feedback\Exceptions\RatingRefused;
use App\Domain\Feedback\Jobs\SendRatingReminder;
use App\Domain\Feedback\Models\OrderRating;
use App\Domain\Notifications\Alerts\AlertKind;
use App\Domain\Notifications\Alerts\OpsAlert;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Notifications\Push\PushTransport;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Events\OrderStatusChanged;
use App\Domain\Ordering\Models\Order;
use App\Domain\Tenancy\Models\Restaurant;
use App\Livewire\Customer\OrderStatus as OrderPage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Records pushes instead of sending them. */
final class RatingPushTransport implements PushTransport
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function configured(): bool
    {
        return true;
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        $this->sent[] = $payload;

        return self::SENT;
    }
}

beforeEach(function () {
    app()->setLocale('en');
});

/** @return array{Restaurant, Customer, Order} a guest with an order picked up just now */
function ratedShop(string $slug = 'masar'): array
{
    $restaurant = restaurant(slug: $slug);
    $guest = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+9745555'.random_int(1000, 9999), 'name' => 'Sara']);

    return [$restaurant, $guest, pastOrder($guest, 4200, now(), attributes: ['locale' => 'en'])];
}

function signInRater(Customer $customer): void
{
    $token = Str::random(64);
    CustomerSession::withoutTenancy()->create(['restaurant_id' => $customer->restaurant_id, 'customer_id' => $customer->id, 'token_hash' => hash('sha256', $token), 'last_active_at' => now()]);
    session()->put("customer.{$customer->restaurant_id}", $token);
    app()->forgetScopedInstances();
}

function ratingRefusal(callable $call): ?string
{
    try {
        $call();
    } catch (RatingRefused $refused) {
        return $refused->reason;
    }

    return null;
}

it('lets the guest rate a picked-up order once, keeping only tags that fit the stars', function () {
    [, $guest, $order] = ratedShop();

    app(RateOrder::class)->handle($order, $guest, 2, ['slow', 'fast'], '  Fries were cold  ');

    expect(OrderRating::withoutTenancy()->sole())
        ->stars->toBe(2)
        ->tags->toBe(['slow'])
        ->comment->toBe('Fries were cold')
        ->branch_id->toBe($order->branch_id)
        ->and(ratingRefusal(fn () => app(RateOrder::class)->handle($order, $guest, 5, [], null)))->toBe('already_rated');
});

it('refuses another guest, an order not picked up, stars out of range, and after 48 hours', function () {
    [$restaurant, $guest, $order] = ratedShop();
    $stranger = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550000']);
    $open = pastOrder($guest, 1000, now(), status: 'preparing');

    expect(ratingRefusal(fn () => app(RateOrder::class)->handle($order, $stranger, 5, [], null)))->toBe('not_yours')
        ->and(ratingRefusal(fn () => app(RateOrder::class)->handle($open, $guest, 5, [], null)))->toBe('not_picked_up')
        ->and(ratingRefusal(fn () => app(RateOrder::class)->handle($order, $guest, 6, [], null)))->toBe('invalid');

    $this->travel(49)->hours();
    expect(ratingRefusal(fn () => app(RateOrder::class)->handle($order, $guest, 5, [], null)))->toBe('too_late')
        ->and(OrderRating::withoutTenancy()->count())->toBe(0);
});

it('raises one low-rating alert for 1 or 2 stars only', function () {
    Notification::fake();
    [$restaurant, $guest, $order] = ratedShop();
    $owner = ownerOf($restaurant);

    app(RateOrder::class)->handle($order, $guest, 1, ['cold'], 'Cold food');
    app(RateOrder::class)->handle(pastOrder($guest, 1000, now()), $guest, 3, [], null);

    Notification::assertSentToTimes($owner, OpsAlert::class, 1);
    Notification::assertSentTo($owner, fn (OpsAlert $alert): bool => $alert->kind === AlertKind::LowRating);
});

it('sends one rating reminder 30 minutes after pickup, and none once rated', function () {
    Queue::fake();
    [, $guest, $order] = ratedShop();

    event(new OrderStatusChanged($order, OrderStatus::Ready, OrderStatus::PickedUp));
    Queue::assertPushed(SendRatingReminder::class, fn (SendRatingReminder $job): bool => $job->orderId === $order->id
        && $job->delay !== null && now()->diffInMinutes($job->delay, true) >= 29);

    $push = new RatingPushTransport;
    app()->instance(PushTransport::class, $push);
    PushSubscription::query()->create(['subscriber_type' => $guest->getMorphClass(), 'subscriber_id' => $guest->id, 'endpoint' => 'https://push.example.test/1', 'public_key' => 'BKey', 'auth_token' => 'auth', 'locale' => 'en']);

    app()->call([new SendRatingReminder($order->id), 'handle']);
    expect($push->sent)->toHaveCount(1)->and($push->sent[0]['title'])->toBe(__('feedback.push.title'));

    app(RateOrder::class)->handle($order, $guest, 5, [], null);
    app()->call([new SendRatingReminder($order->id), 'handle']);
    expect($push->sent)->toHaveCount(1);
});

it('shows the rating card on the order page and saves from it', function () {
    [$restaurant, $guest, $order] = ratedShop();
    signInRater($guest);

    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])
        ->assertSee(__('feedback.card.title'))
        ->call('rate', 5, ['fast'], 'Great')
        ->assertSee(__('feedback.thanks.title'))
        ->assertDontSee(__('feedback.card.title'));

    expect(OrderRating::withoutTenancy()->sole()->tags)->toBe(['fast']);
});

it('keeps ratings to their restaurant and to the guest who ordered', function () {
    [$restaurant, $guest, $order] = ratedShop();
    [$other, $theirGuest] = ratedShop('elsewhere');
    signInRater($theirGuest);

    Livewire::test(OrderPage::class, ['restaurant' => $other, 'order' => $order])->assertNotFound();

    $stranger = Customer::withoutTenancy()->create(['restaurant_id' => $restaurant->id, 'phone' => '+97455550001']);
    signInRater($stranger);
    Livewire::test(OrderPage::class, ['restaurant' => $restaurant, 'order' => $order])
        ->assertDontSee(__('feedback.card.title'))
        ->call('rate', 5, [], null)
        ->assertForbidden();
});
