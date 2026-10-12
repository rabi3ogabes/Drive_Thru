<?php

declare(strict_types=1);

return [
    'guest' => [
        'received' => ':restaurant received your order :number. It will be ready around :when. Follow it here: :link',
        'ready' => 'Your order :number is ready at :branch. Drive up to the window and show pickup code :code.',
        'receipt' => 'Thank you for ordering from :restaurant. Order :number, total :total. Your receipt: :link',
    ],
    'push' => [
        'ready_title' => 'Order :number is ready',
        'ready_body' => 'Drive up to the window at :branch and show pickup code :code.',
    ],
];
