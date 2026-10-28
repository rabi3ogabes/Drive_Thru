<?php

return [
    'title' => 'Install Drive-Thru Plus',
    'eyebrow' => 'First-time setup',
    'lead' => 'Three minutes, once. This page creates the database tables and your super admin account, then locks itself for good.',
    'language' => 'العربية',
    'steps' => [
        'server' => 'Server check',
        'key' => 'Setup key',
        'database' => 'Database',
        'admin' => 'Your super admin account',
    ],
    'server' => [
        'ok' => 'Everything this server needs is in place.',
        'missing' => 'Fix the items marked below in hPanel, then reload this page.',
        'php' => 'PHP version: Advanced → PHP Configuration.',
        'ext' => 'Extensions: Advanced → PHP Configuration → PHP extensions.',
        'writable' => 'Folders must be writable (permissions 755).',
    ],
    'key' => [
        'body' => 'To prove this is your server, open the file below in Hostinger’s File Manager and paste what it contains.',
        'label' => 'Setup key',
    ],
    'database' => [
        'body' => 'From hPanel → Databases → Management. The name and user include the u123… prefix.',
        'host_hint' => 'Leave “localhost” on Hostinger.',
    ],
    'admin' => [
        'body' => 'The owner of the whole platform. Use an email you check; you will sign in with it at /admin.',
        'password_hint' => 'At least 10 characters, with upper and lower case letters and a number.',
    ],
    'attributes' => [
        'key' => 'setup key',
        'db_host' => 'database host',
        'db_port' => 'port',
        'db_database' => 'database name',
        'db_username' => 'database user',
        'db_password' => 'database password',
        'name' => 'full name',
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'password again',
    ],
    'submit' => 'Install',
    'working' => 'Installing… this can take a minute',
    'errors' => [
        'key' => 'That setup key doesn’t match. Copy it again from the file, without spaces.',
        'database' => 'Couldn’t connect to the database: :reason',
        'env_value' => 'The setting :key couldn’t be saved exactly. If it is the password, choose one without quotes and try again.',
        'env_write' => 'The .env file couldn’t be saved. Make sure the app folder is writable, then try again.',
        'migrate' => 'Creating the tables stopped: :reason. Nothing is lost; fix it and press Install again.',
    ],
    'reasons' => [
        'access_denied' => 'the user or password is wrong.',
        'unknown_database' => 'there is no database with that name.',
        'unreachable' => 'the host doesn’t answer. On Hostinger, use localhost.',
        'other' => 'check the details and try again.',
    ],
    'done' => [
        'title' => 'Drive-Thru Plus is installed',
        'body' => 'The tables are created and :email is your super admin. This page is now locked for good.',
        'cta' => 'Sign in to the admin',
        'next' => 'Next',
        'next_items' => [
            'Add the cron job in hPanel → Advanced → Cron Jobs, every minute: php artisan schedule:run',
            'Turn on two-step sign-in in your profile after signing in.',
        ],
        'storage' => 'Product photos need a public link that this host didn’t allow. Ask your host to enable symlinks, or run “php artisan storage:link” over SSH.',
    ],
];
