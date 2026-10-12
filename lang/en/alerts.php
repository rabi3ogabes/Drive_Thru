<?php

declare(strict_types=1);

return [
    'open' => 'Open',

    'order_delayed' => [
        'title' => 'Order :number is running late',
        'body' => ':minutes min past its promised time at :branch.',
    ],
    'guest_waiting' => [
        'title' => 'Guest waiting for order :number',
        'body' => 'At the window at :branch for :minutes min.',
    ],
    'refund_failed' => [
        'title' => 'Refund failed for order :number',
        'body' => 'The provider did not return :amount at :branch. Retry it or settle it with the guest.',
    ],
    'webhook_disabled' => [
        'title' => 'A webhook was switched off',
        'body' => 'Deliveries to :url failed for 3 days, so they stopped. Fix the receiving system, then turn the webhook back on.',
    ],
    'kitchen_overloaded' => [
        'title' => 'Kitchen is overloaded at :branch',
        'body' => ':count orders are waiting. Consider pausing new orders.',
    ],

    'preferences' => [
        'menu' => 'Alerts',
        'title' => 'Alerts',
        'subheading' => 'How you hear about late orders, guests waiting at the window and failed refunds.',
        'device_heading' => 'This device',
        'device_hint' => 'Get alerts on this browser even when the workspace is closed.',
        'device' => [
            'loading' => 'Checking this browser…',
            'on' => 'Alerts are on for this browser.',
            'off' => 'Alerts are off for this browser.',
            'denied' => 'This browser blocks notifications from this site. Allow them in the browser\'s site settings, then come back.',
            'unsupported' => 'This browser can\'t receive push notifications. On iPhone, add the workspace to your Home Screen first.',
            'unconfigured' => 'Push notifications aren\'t set up on this platform yet.',
            'error' => 'Couldn\'t turn alerts on. Try again in a moment.',
            'enable' => 'Turn on',
            'disable' => 'Turn off',
        ],
        'channels_heading' => 'Where alerts reach you',
        'channels_hint' => 'You only get alerts for the branches you work in. Quiet hours, set by the restaurant, hold routine alerts back.',
        'bell' => 'Notification bell',
        'bell_hint' => 'Every alert, with a soft chime while the workspace is open.',
        'always_on' => 'Always on',
        'push' => 'Push notifications',
        'push_hint' => 'On every browser where you turned alerts on.',
        'email' => 'Email for critical alerts',
        'email_hint' => 'Failed refunds and other money matters.',
        'whatsapp' => 'WhatsApp',
        'whatsapp_hint' => 'Every alert on WhatsApp, to the phone on your profile.',
        'whatsapp_needs_phone' => 'Add your phone number in your profile to get alerts on WhatsApp.',
        'saved' => 'Saved',
    ],

    'mail' => [
        'greeting' => 'Hello :name,',
        'action' => 'Open the live board',
        'footer' => 'You get this email because critical alerts by email are on. You can change it under Alerts in your account.',
    ],
];
