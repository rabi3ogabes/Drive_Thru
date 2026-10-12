<?php

return [
    'label' => 'Team member',
    'plural' => 'Platform team',
    'navigation' => 'Team',
    'super_admin' => 'Super admin',

    'sections' => [
        'person' => 'Person',
    ],

    'fields' => [
        'name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Mobile',
        'locale' => 'Language',
        'role' => 'Role',
        'two_factor' => '2FA',
        'last_seen' => 'Last seen',
    ],

    'status' => [
        'active' => 'Active',
        'invited' => 'Invited',
        'suspended' => 'Suspended',
    ],

    'actions' => [
        'invite' => 'Invite member',
        'unlock' => 'Unlock sign-in',
        'unlock_description' => 'Lets this person try to sign in again right away.',
        'reset_two_factor' => 'Reset 2FA',
        'reset_two_factor_description' => 'For a lost phone: they’ll set up a new authenticator at their next sign-in.',
        'suspend' => 'Suspend access',
        'suspend_description' => 'They are signed out everywhere immediately and can’t sign in until reactivated.',
        'reactivate' => 'Reactivate access',
    ],

    'notifications' => [
        'unlocked' => 'Sign-in unlocked',
        'not_locked' => 'This account wasn’t locked',
        'two_factor_reset' => 'Two-factor authentication reset',
        'suspended' => ':name can no longer sign in',
        'reactivated' => ':name can sign in again',
    ],

    'roles' => [
        'label' => 'Role',
        'plural' => 'Roles',
        'system' => 'Built-in',
        'empty' => 'No roles yet',
        'permissions_count' => '{1} 1 permission|[2,*] :count permissions',
        'sections' => [
            'identity' => 'Role',
            'permissions' => 'What this role can do',
            'permissions_hint' => 'Grouped by area. The super admin always has every permission.',
        ],
        'fields' => [
            'name' => 'Name',
            'description' => 'Description',
            'permissions' => 'Permissions',
            'members' => 'Members',
        ],
    ],
];
