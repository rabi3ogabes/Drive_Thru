<?php

return [
    'actions' => [
        'home' => 'Take me home',
        'back' => 'Go back',
    ],
    401 => ['title' => 'Please sign in', 'message' => 'You need to be signed in to see this page.'],
    403 => ['title' => 'This area is private', 'message' => 'Your account doesn’t have access here. If you think it should, ask your manager or the restaurant owner.'],
    404 => ['title' => 'We couldn’t find that', 'message' => 'The link may be old or mistyped. Let’s get you back on the road.'],
    419 => ['title' => 'This page expired', 'message' => 'For your security the page timed out. Go back, refresh and try again.'],
    429 => ['title' => 'Slow down a little', 'message' => 'Too many requests in a short time. Please wait a moment and try again.'],
    500 => ['title' => 'Something went wrong', 'message' => 'It’s on us, not you. Our team has been notified. Please try again in a moment.'],
    503 => ['title' => 'Back very soon', 'message' => 'We’re making things better. The service will be back in a few minutes.'],
];
