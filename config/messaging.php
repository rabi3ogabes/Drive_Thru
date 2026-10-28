<?php

declare(strict_types=1);

return [
    /*
    | Outside production, a channel without credentials logs the message
    | instead of sending it (the text itself is never logged).
    */
    'log_when_unconfigured' => (bool) env('MESSAGING_LOG_FALLBACK', env('APP_ENV', 'production') !== 'production'),

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'), // verifies webhook signatures
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'), // webhook subscription check

        // Approved message templates (Meta requires templates for messages we start).
        'templates' => [
            'otp' => env('WHATSAPP_TEMPLATE_OTP', 'dtp_login_code'),
            'order_received' => env('WHATSAPP_TEMPLATE_RECEIVED', 'dtp_order_received'),
            'order_ready' => env('WHATSAPP_TEMPLATE_READY', 'dtp_order_ready'),
            'receipt' => env('WHATSAPP_TEMPLATE_RECEIPT', 'dtp_order_receipt'),
            'alert' => env('WHATSAPP_TEMPLATE_ALERT', 'dtp_staff_alert'),
            'pager_ready' => env('WHATSAPP_TEMPLATE_PAGER_READY', 'dtp_pager_ready'),
            'stamps' => env('WHATSAPP_TEMPLATE_STAMPS', 'dtp_stamp_card'),
            // Marketing: {{1}} restaurant, {{2}} the offer, {{3}} order link, {{4}} unsubscribe link.
            'campaign' => env('WHATSAPP_TEMPLATE_CAMPAIGN', 'dtp_offer'),
        ],

        // Template language codes as registered with Meta.
        'languages' => ['ar' => 'ar', 'en' => 'en'],
    ],

    'smsala' => [
        'base_url' => env('SMSALA_BASE_URL', 'https://api2.smsala.com'),
        'api_token' => env('SMSALA_API_TOKEN'),
        'sender_id' => env('SMSALA_SENDER_ID'),
    ],
];
