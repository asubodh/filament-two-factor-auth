<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the Two-Factor Authentication plugin
    | for your Filament admin panel.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Issuer Name
    |--------------------------------------------------------------------------
    |
    | The name displayed in the user's authenticator app alongside the
    | TOTP entry. Defaults to your application name.
    |
    */

    'issuer' => env('TWO_FACTOR_ISSUER', config('app.name', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | TOTP Verification Window
    |--------------------------------------------------------------------------
    |
    | The number of 30-second periods to check on either side of the
    | current timestamp. A value of 1 allows ±30 seconds of clock
    | drift between the server and authenticator app.
    |
    */

    'window' => env('TWO_FACTOR_WINDOW', 1),

    /*
    |--------------------------------------------------------------------------
    | Encrypt Secret at Rest
    |--------------------------------------------------------------------------
    |
    | When enabled, TOTP secrets are encrypted using Laravel's Crypt
    | facade before being stored in the database. Highly recommended
    | for production environments.
    |
    */

    'encrypt_secret' => env('TWO_FACTOR_ENCRYPT_SECRET', true),

    /*
    |--------------------------------------------------------------------------
    | Recovery Codes
    |--------------------------------------------------------------------------
    |
    | Configuration for the one-time-use recovery codes that allow
    | users to regain access if they lose their authenticator device.
    |
    */

    'recovery_codes' => [

        // Number of recovery codes to generate
        'count' => 8,

        // Character length of each recovery code
        'length' => 10,

        // Table name for storing recovery codes
        'table' => 'two_factor_recovery_codes',

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Protect against brute-force OTP guessing by limiting the number
    | of verification attempts within a given time window.
    |
    */

    'rate_limit' => [

        // Maximum OTP attempts before lockout
        'max_attempts' => 5,

        // Lockout duration in minutes
        'decay_minutes' => 1,

    ],

    /*
    |--------------------------------------------------------------------------
    | Remember Device (Trusted Device)
    |--------------------------------------------------------------------------
    |
    | When enabled, users can mark a device as trusted after successful
    | 2FA verification, skipping the OTP challenge for subsequent
    | visits within the configured duration.
    |
    */

    'remember_device' => [

        'enabled' => env('TWO_FACTOR_REMEMBER_DEVICE', false),

        // Number of days the trusted device cookie persists
        'days' => 30,

        // Cookie name
        'cookie' => 'two_factor_trusted_device',

    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge Route
    |--------------------------------------------------------------------------
    |
    | The route path for the 2FA challenge page. This is appended
    | to your Filament panel's path prefix.
    |
    */

    'challenge_route' => 'two-factor-challenge',

    /*
    |--------------------------------------------------------------------------
    | Settings Route
    |--------------------------------------------------------------------------
    |
    | The route path for the 2FA settings page within the panel.
    |
    */

    'settings_route' => 'two-factor-settings',

];
