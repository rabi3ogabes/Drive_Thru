<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Messaging\Models\MessageDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Delivery reports from the messaging providers:
 * - Meta WhatsApp: subscription check (GET) and status updates (POST),
 *   accepted only with a valid X-Hub-Signature-256 from our app secret;
 * - SMSala: our own signed callback URL per delivery (SMSala doesn't sign).
 */
final class MessagingWebhookController
{
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

    private function apply(string $providerId, ?string $status, mixed $error): void
    {
        if ($providerId === '' || $status === null) {
            return;
        }

        MessageDelivery::withoutTenancy()->where('provider_message_id', $providerId)->first()
            ?->report($status, is_string($error) ? $error : null);
    }
}
