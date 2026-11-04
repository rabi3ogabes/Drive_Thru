<?php

declare(strict_types=1);

return [
    'card' => [
        'title' => 'How was your order?',
        'subtitle' => 'Thirty seconds, and the team at :branch will read it.',
        'stars' => '{1} 1 star|[2,*] :count stars',
        'scale' => [1 => 'Poor', 2 => 'Not great', 3 => 'Okay', 4 => 'Good', 5 => 'Excellent'],
        'what_went_well' => 'What went well?',
        'what_went_wrong' => 'What went wrong?',
        'comment' => 'Anything else? (optional)',
        'comment_placeholder' => 'Tell the team in a few words',
        'send' => 'Send rating',
        'closes' => 'You can rate until :time.',
    ],
    'tags' => [
        'fast' => 'Fast',
        'hot_fresh' => 'Hot and fresh',
        'friendly' => 'Friendly',
        'accurate' => 'Order was right',
        'slow' => 'Slow',
        'cold' => 'Cold',
        'missing_item' => 'Missing item',
        'wrong_order' => 'Wrong order',
        'unfriendly' => 'Unfriendly',
    ],
    'thanks' => [
        'title' => 'Thank you',
        'body' => 'Your rating went straight to the team.',
        'low' => 'Sorry it wasn’t right. The manager has been told.',
    ],
    'push' => [
        'title' => 'How was your order?',
        'body' => 'Rate your order from :restaurant in a tap.',
    ],
    'errors' => [
        'not_yours' => 'Only the guest who ordered can rate this order.',
        'not_picked_up' => 'You can rate the order once you have picked it up.',
        'too_late' => 'Ratings close 48 hours after pickup.',
        'already_rated' => 'You have already rated this order. Thank you.',
        'invalid' => 'Choose from 1 to 5 stars.',
    ],
];
