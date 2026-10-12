<?php

return [
    'vehicle_type' => [
        'car' => 'Car',
        'suv' => 'SUV',
        'pickup' => 'Pickup',
        'van' => 'Van',
        'motorbike' => 'Motorbike',
    ],
    'devices' => [
        'device' => 'Device',
    ],
    'otp' => [
        'message' => 'Your :restaurant code is :code. It expires in :minutes minutes. Never share it.',
        'errors' => [
            'phone' => 'Enter a valid mobile number.',
            'wait' => 'Please wait :seconds seconds before asking for a new code.',
            'limit' => 'Too many codes requested. Try again in :minutes minutes.',
            'delivery' => 'We couldn’t send a code right now. Please try again in a moment.',
            'expired' => 'This code has expired. Ask for a new one.',
            'wrong' => '{1} That code isn’t right. 1 try left.|[2,*] That code isn’t right. :count tries left.',
            'too_many' => 'Too many wrong tries. Ask for a new code.',
        ],
    ],
];
