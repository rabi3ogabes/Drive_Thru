<?php

return [

    /*
    | Outgoing webhooks to other systems (POS, delivery hubs).
    */
    'webhooks' => [
        // Refuse URLs that point at this server, private networks or cloud metadata.
        'block_private_networks' => (bool) env('WEBHOOKS_BLOCK_PRIVATE_NETWORKS', true),
        'timeout_seconds' => 10,
        // An endpoint with only failures for this long is switched off (the owner is told).
        'disable_after_hours' => 72,
        'max_endpoints' => 10,
    ],

    /*
    | Public REST API (v1) for POS and other systems.
    */
    'api' => [
        'requests_per_minute' => (int) env('API_REQUESTS_PER_MINUTE', 120),
        'max_clients' => 20,
    ],

];
