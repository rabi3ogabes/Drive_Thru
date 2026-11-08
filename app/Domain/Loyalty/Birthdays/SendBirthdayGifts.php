<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Birthdays;

use App\Domain\Crm\UnsubscribeLink;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Actions\GrantReward;
use App\Domain\Loyalty\Exceptions\RewardUnavailable;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Notifications\Push\WebPushSender;
use App\Domain\Tenancy\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The daily birthday run (loyalty:birthdays, 06:00 Doha): guests whose
 * birthday is N days away in the restaurant's time zone get the gift in
 * their wallet. Once per guest per 365 days, whatever they do with their
 * birthday; running twice in a day gives nothing more. A message goes only
 * to guests who accepted offers; a push to guests who allowed notifications.
 */
final class SendBirthdayGifts
{
    public const ONCE_EVERY_DAYS = 365;

    public function __construct(
        private readonly GrantReward $grant,
        private readonly Messenger $messenger,
        private readonly WebPushSender $push,
    ) {}

    /** Every restaurant with the gift switched on. */
    public function runAll(CarbonImmutable $now): int
    {
        $sent = 0;

        Restaurant::query()->where('settings->birthday->enabled', true)->orderBy('id')->each(function (Restaurant $restaurant) use ($now, &$sent): void {
            $sent += $this->run($restaurant, $now);
        });

        return $sent;
    }

    public function run(Restaurant $restaurant, CarbonImmutable $now): int
    {
        $settings = BirthdaySettings::for($restaurant);
        $reward = $settings->isLive() ? $settings->reward() : null;

        if ($reward === null) {
            return 0;
        }

        $target = $now->setTimezone($restaurant->timezone)->startOfDay()->addDays($settings->daysBefore);
        $sent = 0;

        $this->due($restaurant, $target)->each(function (Customer $guest) use ($restaurant, $reward, $settings, $target, $now, &$sent): void {
            if ($this->give($restaurant, $guest, $reward, $target, $now)) {
                $sent++;
                $this->tell($restaurant, $guest, $reward, $settings, $target, $now);
            }
        });

        return $sent;
    }

    /**
     * Guests whose birthday falls on $target, and who had no gift in the last 365 days.
     *
     * @return Builder<Customer>
     */
    private function due(Restaurant $restaurant, CarbonImmutable $target): Builder
    {
        // 29 February birthdays are celebrated on 28 February in other years.
        $leapDay = $target->month === 2 && $target->day === 28 && ! $target->isLeapYear();

        return Customer::withoutTenancy()
            ->where('restaurant_id', $restaurant->id)
            ->whereNotNull('birthday')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereMonth('birthday', $target->month)->whereDay('birthday', $target->day))
                ->when($leapDay, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query->whereMonth('birthday', 2)->whereDay('birthday', 29))))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('birthday_gifts')
                ->whereColumn('birthday_gifts.customer_id', 'customers.id')
                ->where('birthday_gifts.sent_at', '>=', $target->subDays(self::ONCE_EVERY_DAYS)->utc()))
            ->orderBy('id');
    }

    private function give(Restaurant $restaurant, Customer $guest, Reward $reward, CarbonImmutable $target, CarbonImmutable $now): bool
    {
        try {
            return DB::transaction(function () use ($restaurant, $guest, $reward, $target, $now): bool {
                $gift = BirthdayGift::withoutTenancy()->create([
                    'restaurant_id' => $restaurant->id,
                    'customer_id' => $guest->id,
                    'year' => $target->year,
                    'sent_at' => $now,
                ]);

                $gift->forceFill(['wallet_reward_id' => $this->grant->handle($guest, $reward, 'birthday', null)->id])->save();

                return true;
            });
        } catch (QueryException) {
            return false; // already given this year (a second run, or two servers at once)
        } catch (RewardUnavailable) {
            return false;
        }
    }

    private function tell(Restaurant $restaurant, Customer $guest, Reward $reward, BirthdaySettings $settings, CarbonImmutable $target, CarbonImmutable $now): void
    {
        $locale = in_array($guest->locale, ['ar', 'en'], true) ? $guest->locale : 'ar';
        $days = (int) $now->setTimezone($restaurant->timezone)->startOfDay()->diffInDays($target, true);
        $words = [
            'name' => $guest->firstName() ?? '',
            'restaurant' => (string) $restaurant->getTranslation('name', $locale),
            'reward' => (string) $reward->getTranslation('name', $locale),
        ];

        if ($settings->sendMessage && $guest->acceptsOffers()) {
            $offer = (string) trans_choice('loyalty.birthday.message', $days, $words + ['days' => $days], $locale);
            $orderUrl = route('customer.rewards', $restaurant);
            $stopUrl = UnsubscribeLink::url($guest, $restaurant);

            // The approved offer template (dtp_offer): restaurant, offer, link, stop link.
            $this->messenger->send(new Message(
                phone: $guest->phone,
                purpose: Message::CAMPAIGN,
                text: $words['restaurant'].': '.$offer."\n".$orderUrl."\n".__('campaigns.message.stop', ['url' => $stopUrl], $locale),
                parameters: [$words['restaurant'], $offer, $orderUrl, $stopUrl],
                locale: $locale,
                restaurantId: $restaurant->id,
                subject: $guest,
            ));
        }

        if ($this->push->available() && $this->push->hasSubscribers($guest)) {
            $this->push->send($guest, [
                'title' => __('loyalty.birthday.push_title', $words, $locale),
                'body' => __('loyalty.birthday.push_body', $words, $locale),
                'url' => route('customer.rewards', $restaurant),
                'tag' => 'birthday-'.$guest->id,
            ], $restaurant->id);
        }
    }
}
