<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff sign-in protection
    |--------------------------------------------------------------------------
    |
    | Failures are counted per email and per IP inside a rolling window. Hitting
    | a limit locks that principal. The IP limit is higher than the email limit
    | so a whole office behind one IP is not locked out by a single user.
    |
    */

    'login' => [
        'max_email_failures' => (int) env('SECURITY_LOGIN_MAX_EMAIL_FAILURES', 5),
        'max_ip_failures' => (int) env('SECURITY_LOGIN_MAX_IP_FAILURES', 20),
        'window_minutes' => (int) env('SECURITY_LOGIN_WINDOW_MINUTES', 15),
        'lock_minutes' => (int) env('SECURITY_LOGIN_LOCK_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    |
    | When enabled, platform staff must set up an authenticator app before they
    | can use the admin panel. Keep it on in production.
    |
    */

    'require_platform_mfa' => (bool) env('SECURITY_REQUIRE_PLATFORM_MFA', false),

];
