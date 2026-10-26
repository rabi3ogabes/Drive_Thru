<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Customers\Models\Customer;
use App\Models\User;

/**
 * Permission to send a guest offers. It is only ever given by the guest
 * (checkout, account page, a QR or invite page) and kept with its proof:
 * when, where and which wording they agreed to. Withdrawing is one tap, a
 * STOP reply or the unsubscribe link, and works at once. Every change is in
 * the audit log.
 */
final class Consent
{
    /** The wording on the consent checkbox; bump it when the text changes. */
    public const VERSION = '2026-10';

    public const SOURCES = ['checkout', 'account', 'qr', 'referral'];

    public const WITHDRAW_SOURCES = ['account', 'unsubscribe_link', 'whatsapp_stop', 'staff'];

    public function give(Customer $customer, string $source): void
    {
        if ($customer->acceptsOffers()) {
            return;
        }

        $source = in_array($source, self::SOURCES, true) ? $source : 'account';

        $customer->forceFill([
            'offers_consent_at' => now(),
            'offers_consent_source' => $source,
            'offers_consent_version' => self::VERSION,
            'offers_withdrawn_at' => null,
            'notification_prefs' => array_merge((array) $customer->notification_prefs, ['offers' => true]),
        ])->save();

        activity()->performedOn($customer)->event('crm.consent_given')
            ->withProperties(['source' => $source, 'version' => self::VERSION])->log('crm.consent_given');
    }

    /** False when there was nothing to withdraw. */
    public function withdraw(Customer $customer, string $source, ?User $by = null): bool
    {
        if ($customer->offers_consent_at === null && ! $customer->wants('offers')) {
            return false;
        }

        $customer->forceFill([
            'offers_consent_at' => null,
            'offers_withdrawn_at' => now(),
            'notification_prefs' => array_merge((array) $customer->notification_prefs, ['offers' => false]),
        ])->save();

        $log = activity()->performedOn($customer)->event('crm.consent_withdrawn')
            ->withProperties(['source' => in_array($source, self::WITHDRAW_SOURCES, true) ? $source : 'account']);

        if ($by !== null) {
            $log->causedBy($by);
        }

        $log->log('crm.consent_withdrawn');

        return true;
    }
}
