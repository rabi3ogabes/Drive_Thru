<?php

declare(strict_types=1);

return [
    /*
    | The Fake gateway lets every payment path be tried without money. It is
    | on outside production and never used in production unless forced.
    */
    'fake_enabled' => (bool) env('PAYMENTS_FAKE', env('APP_ENV', 'production') !== 'production'),

    // Online orders still unpaid after this many minutes are checked with the provider, then cancelled.
    'pending_timeout_minutes' => (int) env('PAYMENTS_PENDING_TIMEOUT', 20),

    // A provider that says "in progress" is asked again until this many hours have passed.
    'pending_max_hours' => 26,

    'sadad' => [
        // Web Checkout 2.1 (same URL for test and live; the secret key decides).
        'checkout_url' => env('SADAD_CHECKOUT_URL', 'https://sadadqa.com/webpurchase'),
        'api' => [
            'test' => env('SADAD_API_TEST_URL', 'https://api-sandbox.sadad.qa/api'),
            'live' => env('SADAD_API_LIVE_URL', 'https://api-s.sadad.qa/api'),
        ],
        // Sadad's refund docs name this field three ways; the parameter table says "transactionno".
        'refund_field' => env('SADAD_REFUND_FIELD', 'transactionno'),
        'partial_refunds' => (bool) env('SADAD_PARTIAL_REFUNDS', false),
    ],
];
