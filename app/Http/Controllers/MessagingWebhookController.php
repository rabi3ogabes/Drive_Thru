<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Crm\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Messaging\Data\Message;
use App\Domain\Messaging\Models\MessageDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Delivery reports from the messaging providers:
 * - Meta WhatsApp: subscription check (GET), status updates and guests'
 *   replies (POST), accepted only with a valid X-Hub-Signature-256 from our
 *   app secret. A STOP / إيقاف reply stops offers;
 * - SMSala: our own signed callback URL per delivery (SMSala doesn't sign).
 */
final class MessagingWebhookController
{
    /** Replies that stop offers, compared after trimming and lower-casing. */
    public const STOP_WORDS = ['stop', 'stop offers', 'unsubscribe', 'إيقاف', 'ايقاف', 'إيقاف العروض', 'ايقاف العروض', 'إلغاء', 'الغاء'];

    /** Meta's one-time subscription check. */
    public function verifyWhatsApp(Request $request): Response
    {
        $token = (string) config('messaging.whatsapp.verify_token');

        abort_unless($token !== '' && $request->query('hub_mode') === 'subscribe' && hash_equals($token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function whatsapp(Request $request): JsonResponse
    {
        $secret = (string) config('messaging.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless($secret !== '' && hash_equals($expected, $signature), 403);

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    $this->apply((string) ($status['id'] ?? ''), match ($status['status'] ?? null) {
                        'sent' => MessageDelivery::SENT,
                        'delivered' => MessageDelivery::DELIVERED,
                        'read' => MessageDelivery::READ,
                        'failed' => MessageDelivery::FAILED,
                        default => null,
                    }, $status['errors'][0]['title'] ?? null);
                }

                foreach ((array) ($change['value']['messages'] ?? []) as $message) {
                    $this->reply((string) ($message['from'] ?? ''), (string) ($message['text']['body'] ?? $message['button']['text'] ?? ''));
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /** SMSala delivery report for one message (route is signed, so it can't be forged). */
    public function smsala(Request $request, int $delivery): JsonResponse
    {
        $row = MessageDelivery::withoutTenancy()->find($delivery);
        $dlr = $request->input('dlrStatus', $request->input('DlrStatus'));

        $status = match (true) {
            in_array($dlr, ['Delivered', '2', 2], true) => MessageDelivery::DELIVERED,
            in_array($dlr, ['Expired', 'Deleted', 'Undeliverable', 'Rejected', '3', '4', '5', '8', 3, 4, 5, 8], true) => MessageDelivery::FAILED,
            in_array($dlr, ['Enroute', 'Accepted', '1', '6', 1, 6], true) => MessageDelivery::SENT,
            default => null,
        };

        if ($row !== null && $status !== null) {
            $row->report($status, $status === MessageDelivery::FAILED ? 'dlr_'.mb_strtolower((string) $dlr) : null);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A STOP reply stops offers from the restaurant whose offer the guest
     * last received on this number. Without one in the last 30 days it stops
     * every restaurant's offers to that phone: when unsure, stop more.
     */
    private function reply(string $from, string $text): void
    {
        $word = mb_strtolower(trim(preg_replace('/[\s\p{P}]+$/u', '', $text) ?? ''));

        if (! in_array($word, self::STOP_WORDS, true) || preg_match('/^\d{6,15}$/', $from) !== 1) {
            return;
        }

        $phone = '+'.$from;
        $restaurantId = MessageDelivery::withoutTenancy()
            ->where('to_hash', hash('sha256', $phone))
            ->where('purpose', Message::CAMPAIGN)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('id')
            ->value('restaurant_id');

        Customer::withoutTenancy()->where('phone', $phone)
            ->when($restaurantId !== null, fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->get()
            ->each(fn (Customer $customer) => app(Consent::class)->withdraw($customer, 'whatsapp_stop'));
    }

    private function apply(string $providerId, ?string $status, mixed $error): void
    {
        if ($providerId === '' || $status === null) {
            return;
        }

        MessageDelivery::withoutTenancy()->where('provider_message_id', $providerId)->first()
            ?->report($status, is_string($error) ? $error : null);
    }
}
