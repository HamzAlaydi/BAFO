<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notifications module configuration: config('bafo.notifications.*')
|--------------------------------------------------------------------------
|
| ARCHITECTURE §11 (catalogue and mechanics), §15.1/§15.2 (the PushNotifier driver).
|
*/

return [

    'push' => [
        // PushNotifier driver (§15.1). Only `log` exists until a Firebase project does.
        'driver' => env('PUSH_DRIVER', 'log'),
        // The log channel the `log` driver writes to (config/logging.php, Platform).
        'log_channel' => 'push',
    ],

    // Catalogue throttles in seconds (§11.3 "Throttle / notes").
    'throttle' => [
        'competition_updated' => 600,     // 1 per 10 min per competition per user
        'competition_extended_auto' => 120, // 1 per 2 min per competition per user (auto extensions)
        'offer_received' => 300,          // digest: 1 per 5 min per competition per user
        'standing_lost_lead' => 60,       // 1 per 60 s per competition per user
        'comment_created_push' => 300,    // 1 push per 5 min per competition per user
    ],

    'devices' => [
        // notifications:prune-devices removes tokens not seen for this many days (§12).
        'stale_after_days' => 90,
    ],

    'mail' => [
        // Public path of the brand mark used by the mail header (served by this module).
        'logo_path' => 'mail/brand/bafo-mark.png',
    ],

];
