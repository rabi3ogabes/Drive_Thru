<?php

declare(strict_types=1);

return [
    'categories' => [
        'missing_item' => 'Missing item',
        'wrong_item' => 'Wrong item',
        'quality' => 'Quality',
        'charged_incorrectly' => 'Charged incorrectly',
        'other' => 'Something else',
    ],

    'guest' => [
        'report' => 'Report a problem',
        'report_hint' => 'Something missing or not right? Tell the branch and they will sort it out.',
        'report_after_rating' => 'Report what was missing or wrong',
        'sheet_title' => 'What went wrong?',
        'which_items' => 'Which items?',
        'describe' => 'Tell us what happened',
        'describe_placeholder' => 'A few words help the team put it right',
        'send' => 'Send to the branch',
        'cancel' => 'Not now',
        'title' => 'Your report :number',
        'sent' => 'Sent. The branch will reply here and by message.',
        'you' => 'You',
        'team' => ':restaurant team',
        'reply_placeholder' => 'Write a reply',
        'reply' => 'Send',
        'status' => [
            'open' => 'Waiting for the branch',
            'answered' => 'The branch replied',
            'resolved' => 'Resolved',
        ],
        'outcome' => [
            'refund' => ':amount refunded to your card.',
            'reward' => ':reward was added to your rewards wallet.',
            'none' => 'Marked as resolved by the branch.',
        ],
        'window' => 'You can report a problem until :date.',
    ],

    'errors' => [
        'not_yours' => 'Only the guest who ordered can report a problem with this order.',
        'not_picked_up' => 'You can report a problem once you have picked up the order.',
        'too_late' => 'Problems can be reported up to 7 days after pickup.',
        'already_open' => 'There is already an open report for this order. Reply in the conversation below.',
        'invalid' => 'Choose what went wrong and add a few words.',
        'closed' => 'This report is resolved. Report a new problem if something else went wrong.',
    ],
];
