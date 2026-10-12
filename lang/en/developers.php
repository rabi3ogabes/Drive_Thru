<?php

declare(strict_types=1);

return [
    'title' => 'Developer API',
    'description' => 'Connect a POS or any other system to Drive-Thru Plus orders and menus with a REST API and signed webhooks.',
    'contents' => 'On this page',
    'intro' => 'Read and update orders and menus for one restaurant from your POS, kiosk or reporting tool, and receive signed webhooks the moment something changes. The restaurant owner creates the keys and webhooks in their workspace.',
    'nav' => [
        'start' => 'Overview',
        'auth' => 'Authentication',
        'scopes' => 'Scopes',
        'endpoints' => 'Endpoints',
        'webhooks' => 'Webhooks',
        'errors' => 'Errors & limits',
    ],
    'facts' => [
        'base' => 'Base URL',
        'format' => 'Format',
        'format_value' => 'JSON. Money in minor units, names in English and Arabic, times in UTC.',
        'limit' => 'Rate limit',
        'limit_value' => ':count requests per minute per key',
    ],
    'auth' => 'Send the API key as a bearer token on every request. A key belongs to one restaurant and only ever sees that restaurant’s data. Keep it on your server, never in an app or web page.',
    'webhooks' => 'Each webhook receives a POST with a JSON body: id (the same across retries, use it to ignore duplicates), type, created_at, restaurant_id and data. Answer with any 2xx status within 10 seconds.',
    'signature' => 'Check every delivery: the :header header holds the time and an HMAC-SHA256 of "time.body" made with your endpoint’s signing secret. Compare in constant time and reject deliveries older than :minutes minutes.',
    'retries' => 'If your system doesn’t answer 2xx, we retry after 1 minute, 5 minutes, 30 minutes, 2 hours, 6 hours and 12 hours. An endpoint that only fails for 3 days is switched off and the owner is told.',
    'errors' => 'Errors always have the same shape, with a stable code you can act on.',
    'status' => [
        401 => 'The key is missing, wrong or revoked.',
        403 => 'The key doesn’t have the scope this endpoint needs.',
        404 => 'Not found in this restaurant.',
        409 => 'Not possible right now (for example an unpaid order can’t be picked up).',
        422 => 'Invalid input; the errors say which field.',
        429 => 'Too many requests; wait for the Retry-After seconds.',
    ],
];
