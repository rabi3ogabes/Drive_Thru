<?php

return [
    'general_manager' => ['name' => 'General Manager', 'description' => 'Runs every branch: team, menu, orders, customers and reports.'],
    'branch_manager' => ['name' => 'Branch Manager', 'description' => 'Runs their branch day to day: team, availability, orders, refunds and branch reports.'],
    'cashier' => ['name' => 'Cashier', 'description' => 'Handles live orders, collects pay-at-pickup payments and serves the window.'],
    'kitchen' => ['name' => 'Kitchen', 'description' => 'Prepares orders on the kitchen screen and marks them ready.'],
    'expeditor' => ['name' => 'Expeditor', 'description' => 'Hands orders to arriving cars and scans pickup codes.'],
    'accountant' => ['name' => 'Accountant', 'description' => 'Reads sales and finance reports and exports them.'],
    'marketing' => ['name' => 'Marketing', 'description' => 'Runs loyalty, promotions and customer campaigns.'],
    'errors' => [
        'empty' => 'Choose at least one permission.',
        'not_system' => 'Only built-in positions can be restored.',
        'system_delete' => 'Built-in positions can be edited but not deleted.',
        'in_use' => 'Move the people in this position to another one first.',
    ],
    'label' => 'position',
    'plural' => 'Positions',
    'subheading' => 'What each role can see and do. Built-in positions can be tuned; add your own for anything else.',
    'built_in' => 'Built-in',
    'custom' => 'Custom',
    'permissions_count' => '{1} 1 permission|[2,*] :count permissions',
    'empty' => 'No positions yet',
    'restore_description' => 'This position goes back to its original permissions. People in it are updated immediately.',
    'delete_description' => 'Only positions nobody holds can be deleted.',
    'sections' => [
        'identity' => 'Name and purpose',
        'permissions' => 'Permissions',
        'permissions_hint' => 'Grouped by area. Greyed-out permissions are ones you don’t hold yourself, so you can’t hand them out.',
    ],
    'fields' => [
        'name' => 'Position name',
        'description' => 'What this person does',
        'permissions' => 'Access',
        'people' => 'People',
    ],
    'actions' => [
        'create' => 'New position',
        'restore' => 'Restore original permissions',
    ],
    'notifications' => [
        'saved' => 'Position saved',
        'restored' => 'Original permissions restored',
    ], ];
