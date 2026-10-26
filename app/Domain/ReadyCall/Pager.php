<?php

declare(strict_types=1);

namespace App\Domain\ReadyCall;

use App\Domain\Branches\Models\Branch;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Messenger;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Domain\ReadyCall\Enums\PagerStatus;
use App\Domain\ReadyCall\Events\PagerBroadcast;
use App\Domain\ReadyCall\Models\PagerTicket;
use App\Domain\Services\Enums\Service;
use App\Support\Lang;
use App\Support\Phone;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The counter's pager. A ticket gets the next number of the day at the
 * branch and a private link for the guest's phone. "Ready" rings that
 * phone (and sends WhatsApp/SMS once, if a number was given); "Call again"
 * rings it again; "Collected" closes it. Old tickets expire on their own.
 * Every change goes to the branch's screens and the guest's page live.
 */
final class Pager
{
    /** Seconds between two calls of the same guest. */
    public const RECALL_COOLDOWN = 20;

    public function __construct(private readonly Messenger $messenger) {}

    /**
     * @throws OperationRefused
     */
    public function create(Branch $branch, ?string $label = null, ?string $phone = null, ?string $locale = null, ?int $userId = null, ?int $orderId = null): PagerTicket
    {
        $branch->loadMissing('restaurant');

        if (! $branch->restaurant->hasService(Service::ReadyCall)) {
            throw OperationRefused::because('pager_service_off');
        }

        $normalized = null;

        if ($phone !== null && trim($phone) !== '') {
            $normalized = Phone::normalize($phone) ?? throw OperationRefused::because('pager_phone');
        }

        $tz = $branch->restaurant->timezone;
        $day = now($tz)->format('Ymd');

        $ticket = DB::transaction(fn (): PagerTicket => PagerTicket::withoutTenancy()->create([
            'restaurant_id' => $branch->restaurant_id,
            'branch_id' => $branch->id,
            'number' => Sequence::next("pager:{$branch->id}:{$day}"),
            'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 60) : null,
            'status' => PagerStatus::Waiting,
            'phone' => $normalized,
            'locale' => in_array($locale, ['ar', 'en'], true) ? $locale : $branch->restaurant->default_locale,
            'token' => Str::random(32),
            'order_id' => $orderId,
            'expires_at' => now()->addHours(PagerTicket::LIFETIME_HOURS),
            'created_by' => $userId,
        ]));

        $this->broadcast($ticket, 'created');

        return $ticket;
    }

    /**
     * The order is ready: ring the guest's phone, and message them once.
     *
     * @throws OperationRefused
     */
    public function ready(PagerTicket $ticket): PagerTicket
    {
        $claimed = PagerTicket::withoutTenancy()->whereKey($ticket->id)
            ->where('status', PagerStatus::Waiting->value)
            ->where('expires_at', '>', now())
            ->update(['status' => PagerStatus::Ready->value, 'ready_at' => now(), 'last_called_at' => now(), 'calls' => 1, 'updated_at' => now()]);

        if ($claimed !== 1) {
            throw OperationRefused::because('pager_not_waiting');
        }

        $ticket = $ticket->fresh(['branch.restaurant']) ?? $ticket;
        $this->broadcast($ticket, 'ready');
        $this->message($ticket);

        return $ticket;
    }

    /**
     * Rings again (not more often than every 20 seconds).
     *
     * @throws OperationRefused
     */
    public function callAgain(PagerTicket $ticket): PagerTicket
    {
        $claimed = PagerTicket::withoutTenancy()->whereKey($ticket->id)
            ->where('status', PagerStatus::Ready->value)
            ->where(fn ($q) => $q->whereNull('last_called_at')->orWhere('last_called_at', '<=', now()->subSeconds(self::RECALL_COOLDOWN)))
            ->update(['last_called_at' => now(), 'calls' => DB::raw('calls + 1'), 'updated_at' => now()]);

        if ($claimed !== 1) {
            throw OperationRefused::because('pager_too_soon');
        }

        $ticket = $ticket->fresh() ?? $ticket;
        $this->broadcast($ticket, 'called');

        return $ticket;
    }

    /**
     * @throws OperationRefused
     */
    public function collected(PagerTicket $ticket): PagerTicket
    {
        $claimed = PagerTicket::withoutTenancy()->whereKey($ticket->id)
            ->whereIn('status', [PagerStatus::Waiting->value, PagerStatus::Ready->value])
            ->update(['status' => PagerStatus::Collected->value, 'collected_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            throw OperationRefused::because('pager_closed');
        }

        $ticket = $ticket->fresh() ?? $ticket;
        $this->broadcast($ticket, 'collected');

        return $ticket;
    }

    /** The guest opened their pager page (shown to the counter as "on their phone"). */
    public function opened(PagerTicket $ticket): void
    {
        if ($ticket->opened_at === null && PagerTicket::withoutTenancy()->whereKey($ticket->id)->whereNull('opened_at')->update(['opened_at' => now()]) === 1) {
            $this->broadcast($ticket, 'opened');
        }
    }

    /** Closes tickets past their lifetime; returns how many. */
    public function expireOld(): int
    {
        $expired = 0;

        PagerTicket::withoutTenancy()
            ->whereIn('status', [PagerStatus::Waiting->value, PagerStatus::Ready->value])
            ->where('expires_at', '<=', now())
            ->each(function (PagerTicket $ticket) use (&$expired): void {
                if (PagerTicket::withoutTenancy()->whereKey($ticket->id)->whereIn('status', [PagerStatus::Waiting->value, PagerStatus::Ready->value])
                    ->update(['status' => PagerStatus::Expired->value, 'updated_at' => now()]) === 1) {
                    $expired++;
                    $this->broadcast($ticket->forceFill(['status' => PagerStatus::Expired]), 'expired');
                }
            });

        return $expired;
    }

    /** The guest's pager link. */
    public function url(PagerTicket $ticket): string
    {
        $ticket->loadMissing('branch.restaurant');

        return route('customer.pager', [$ticket->branch->restaurant, $ticket->token]);
    }

    private function message(PagerTicket $ticket): void
    {
        if ($ticket->phone === null) {
            return;
        }

        $restaurant = $ticket->branch->restaurant;
        $branch = $ticket->branch->getTranslation('name', $ticket->locale);
        $number = (string) $ticket->number;

        try {
            $this->messenger->send(new Message(
                phone: $ticket->phone,
                purpose: Message::PAGER_READY,
                text: Lang::text('notifications.guest.pager_ready', ['number' => $number, 'branch' => $branch, 'link' => $this->url($ticket)], $ticket->locale),
                parameters: [$number, $branch],
                locale: $ticket->locale,
                restaurantId: $restaurant->id,
                subject: $ticket,
            ));
        } catch (Throwable $e) {
            report($e); // the phone page still rings; a failed message never blocks the counter
        }
    }

    private function broadcast(PagerTicket $ticket, string $what): void
    {
        try {
            event(PagerBroadcast::for($ticket, $what));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
