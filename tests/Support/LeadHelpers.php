<?php

use App\Domain\Growth\LeadSpamGuard;

/* Shared by the request-form tests: a form token minted $seconds ago, as if the page had been open that long. */

if (! function_exists('leadToken')) {
    function leadToken(int $seconds = 20): string
    {
        test()->travel(-$seconds)->seconds();
        $token = app(LeadSpamGuard::class)->token();
        test()->travelBack();

        return $token;
    }
}
