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
    'admin' => [
        'label' => 'rating',
        'plural' => 'Ratings',
        'subheading' => 'What guests said after pickup. 1 and 2 stars alert the branch managers.',
        'columns' => [
            'date' => 'Rated',
            'stars' => 'Stars',
            'tags' => 'Tags',
            'comment' => 'Comment',
            'branch' => 'Branch',
            'guest' => 'Guest',
        ],
        'filters' => [
            'has_comment' => 'Has a comment',
        ],
        'summary' => [
            'average' => 'Average, last 30 days',
            'count' => 'Ratings',
            'happy' => 'Rated 4 or 5',
            'low' => 'Rated 1 or 2',
            'distribution' => 'Scores',
            'tags' => 'Mentioned most',
            'no_tags' => 'No tags picked yet.',
            'trend' => 'Ratings per day',
        ],
        'empty' => [
            'heading' => 'No ratings yet',
            'description' => 'Guests are asked how it went on their order page after pickup.',
        ],
        'stat' => 'Rating, 30 days',
        'stat_none' => 'No ratings yet',
        'stat_count' => '{1} 1 rating|[2,*] :count ratings',
        'profile' => 'Ratings',
    ],
    'errors' => [
        'not_yours' => 'Only the guest who ordered can rate this order.',
        'not_picked_up' => 'You can rate the order once you have picked it up.',
        'too_late' => 'Ratings close 48 hours after pickup.',
        'already_rated' => 'You have already rated this order. Thank you.',
        'invalid' => 'Choose from 1 to 5 stars.',
    ],
];
