<?php

return [
    'label' => 'Plan',
    'plural' => 'Plans',
    'per_branch_per' => 'per branch / :interval',
    'no_trial' => 'No trial',
    'trial_days_count' => '{1} :count-day trial|[2,*] :count-day trial',
    'limits_summary' => ':branches branches · :employees staff',

    'sections' => [
        'identity' => 'Name & description',
        'identity_hint' => 'How the plan appears to clients and on the pricing page.',
        'price' => 'Price & limits',
        'price_hint' => 'Prices are per branch. Existing clients keep their price until you change their plan.',
        'features' => 'Included features',
        'features_hint' => 'Clients can also get individual features switched on or off from their page.',
        'visibility' => 'Visibility',
    ],

    'fields' => [
        'name' => 'Name',
        'description' => 'Description',
        'key' => 'Key',
        'key_hint' => 'A short permanent identifier, e.g. "growth". It can’t be changed later.',
        'price' => 'Price',
        'price_hint' => 'Leave empty for custom pricing (Enterprise).',
        'currency' => 'Currency',
        'interval' => 'Billed every',
        'trial_days' => 'Free trial',
        'trial' => 'Trial',
        'days' => 'days',
        'max_branches' => 'Max branches',
        'max_employees' => 'Max team accounts',
        'unlimited_hint' => 'Leave empty for unlimited.',
        'limits' => 'Limits',
        'features' => 'Features',
        'subscribers' => 'Clients',
        'is_active' => 'Available',
        'is_active_hint' => 'Can be chosen for new and existing clients.',
        'is_public' => 'Public',
        'is_public_hint' => 'Shown on the website and in the request form.',
        'is_highlighted' => 'Most popular',
        'is_highlighted_hint' => 'Highlighted on the pricing page.',
        'sort' => 'Order',
    ],

    'empty' => [
        'heading' => 'No plans yet',
        'description' => 'Create the plans clients subscribe to.',
    ],
];
