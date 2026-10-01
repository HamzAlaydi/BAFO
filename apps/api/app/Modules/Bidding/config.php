<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Bidding module configuration: config('bafo.bidding.*')
|--------------------------------------------------------------------------
|
| The engine rules are ARCHITECTURE §7. The admin-editable values are runtime settings
| (§15.3), registered below as defaults; read them with app(Settings::class)->get($key).
|
*/

return [

    // Participant presence for the issuer's "online participants" (ARCHITECTURE §9.5).
    'heartbeat' => [
        'ttl_seconds' => 45,
        // API.md §1.6: at most 1 per 10 s per user; excess calls are answered 204 too.
        'min_interval_seconds' => 10,
    ],

    // §7.4 step 5: a rejected attempt is cached per (participant, Idempotency-Key) for 24 h.
    'rejection_cache_hours' => 24,

    // ARCHITECTURE §2.2 item 7: the `offers` limiter, per user.
    'offers_per_minute' => 60,

    // The unique lock of the BAFO end job (the tick re-dispatches after it expires).
    'end_bafo_unique_seconds' => 30,

    // Defaults of the Bidding runtime settings (ARCHITECTURE §15.3).
    'settings' => [
        'bidding.max_amount_minor' => 1_000_000_000_000,
        'bidding.offer_min_interval_seconds' => 2,
        'bidding.outlier_guard_bps' => 2000,
        'bidding.auto_extend_bounds' => [
            'window_min' => 60, 'window_max' => 1800,
            'by_min' => 60, 'by_max' => 1800,
            'max_min' => 1, 'max_max' => 50,
        ],
        'bidding.bafo_duration_bounds' => ['min' => 15, 'max' => 4320],
        'bidding.max_concurrent_live' => 30,
        'bidding.closing_soon_minutes' => [10, 2],
    ],

];
