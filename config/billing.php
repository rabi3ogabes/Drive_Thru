<?php

declare(strict_types=1);

return [
    /*
     * Whether the daily billing run (billing:run at 05:30, Doha time) is scheduled.
     * Switch it off with BILLING_SCHEDULE=false while billing is not live yet;
     * php artisan billing:run still works by hand.
     */
    'scheduled' => (bool) env('BILLING_SCHEDULE', true),
];
