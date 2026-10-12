<?php

declare(strict_types=1);

return [
    // VAPID keys: create them once with `php artisan webpush:keys` (writes .env). Never commit them.
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'hello@example.com')),
];
