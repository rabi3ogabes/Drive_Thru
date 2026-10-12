<?php

return [
    'status' => [
        'pending_payment' => 'Awaiting payment',
        'scheduled' => 'Scheduled',
        'received' => 'Received',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'picked_up' => 'Picked up',
        'cancelled' => 'Cancelled',
    ],
    'transition' => [
        'not_allowed' => 'That status change isn’t allowed (:from → :to).',
        'stale' => 'This order was just updated on another screen. The latest status is shown now.',
        'reason_required' => 'Please give a reason for this change.',
    ],
    'payment_method' => [
        'card' => 'Card',
        'apple_pay' => 'Apple Pay',
        'google_pay' => 'Google Pay',
        'pay_at_pickup' => 'Pay at the window',
    ],
    'to_collect' => 'To collect',
    'payment_status' => [
        'unpaid' => 'Pay at pickup',
        'pending' => 'Payment pending',
        'paid' => 'Paid',
        'failed' => 'Payment failed',
        'refunded' => 'Refunded',
    ],
    'timing' => [
        'now' => ['label' => 'Start now', 'description' => 'Ready as soon as possible.'],
        'delay' => ['label' => 'Start later', 'description' => 'Choose when the kitchen starts.'],
        'on_the_way' => ['label' => 'I’ll tell you', 'description' => 'We start when you tap “I’m on the way”.'],
        'scheduled' => ['label' => 'Pick a time', 'description' => 'Any time in the next 7 days.'],
    ],
    'cart' => [
        'errors' => [
            'gone' => 'This item is no longer on the menu.',
            'stock' => 'Only :count left today.',
            'option' => 'Please check your choices for “:group”.',
            'rule' => 'Please complete your choices for “:group”.',
            'options_changed' => 'The options for this item changed. Please add it again.',
            'meal_part' => ':item isn’t available right now. Please choose another.',
        ],
    ],
    'errors' => [
        'branch' => 'This branch isn’t part of this restaurant.',
        'restaurant_paused' => 'This restaurant isn’t taking orders right now.',
        'empty' => 'Your bag is empty.',
        'capacity' => 'The kitchen is fully booked for that time. The next free time is :time.',
        'cart_changed' => 'Something in your bag changed. Please review it and try again.',
        'total_changed' => 'Your total just changed (an offer ended or a price was updated). Please check the new total and place your order again.',
        'benefit_changed' => 'Your total changed: a code, reward or points you chose can no longer be used as shown. Please check your total and try again.',
        'sold_out' => ':item just sold out. Please remove it and try again.',
        'closed' => 'The branch isn’t taking orders at that time. It opens again at :time.',
        'delay' => 'Choose between :min and :max minutes.',
        'schedule' => 'Choose a time at least 15 minutes from now and within :days days.',
        'cancel_window' => 'This order can no longer be cancelled here. Please call the branch.',
    ],
];
