<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Identity module configuration: config('bafo.identity.*')
|--------------------------------------------------------------------------
|
| Environment keys: ARCHITECTURE §15.2 (only OTP_FAKE_CODE). The other values are the fixed
| rules of ARCHITECTURE §13.8–§13.10, kept here so they have one home.
|
*/

return [

    'otp' => [
        // Used instead of a random code only when APP_ENV is local or testing (§13.9).
        'fake_code' => env('OTP_FAKE_CODE'),
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'max_sends_per_hour' => 5,
    ],

    // Team invitation links expire after this many days (§5.3 `memberships.invite_expires_at`).
    'team_invitation_ttl_days' => 7,

    // An account deletion request runs this many days after it is made (§13.8).
    'account_deletion_grace_days' => 14,

];
