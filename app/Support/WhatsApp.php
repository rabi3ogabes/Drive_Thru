<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Click-to-chat links (wa.me) for sharing invitations and the like from the
 * console, without any WhatsApp API account.
 */
final class WhatsApp
{
    public static function shareUrl(?string $phone, string $message): string
    {
        $digits = preg_replace('/\D+/', '', Digits::toLatin((string) $phone)) ?? '';

        // Local Qatari numbers (8 digits) get the country code.
        if (strlen($digits) === 8) {
            $digits = '974'.$digits;
        }

        return 'https://wa.me/'.($digits !== '' ? $digits : '').'?text='.rawurlencode($message);
    }
}
