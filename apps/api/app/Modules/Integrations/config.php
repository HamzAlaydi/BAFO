<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integrations module configuration: config('bafo.integrations.*')
|--------------------------------------------------------------------------
|
| Environment keys: ARCHITECTURE §15.2 (API_KEY_ENV, WEBHOOKS_ALLOW_PRIVATE_TARGETS).
| Everything else is a fixed contract value (ARCHITECTURE §14), kept here so that it has
| one home and tests can read it.
|
*/

return [

    // `bafo_{env}_…` API keys and the webhook envelope `environment` (§14.1, API.md §4.2): live|test.
    'key_environment' => env('API_KEY_ENV', 'test'),

    'oauth' => [
        // Access-token lifetime of the client-credentials grant (§14.2).
        'token_ttl_minutes' => 30,
    ],

    'api_keys' => [
        'default_ttl_days' => 365,
        'max_ttl_days' => 730,
    ],

    // Public API rate limits (§14.3).
    'rate_limits' => [
        'per_minute_read' => 600,
        'per_minute_write' => 300,
        'per_minute_organization' => 1500,
        'token_per_minute' => 20,
    ],

    'webhooks' => [
        // Honoured only when APP_ENV is not production (§14.6): allows http and private or
        // loopback targets for local development.
        'allow_private_targets' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_TARGETS', false),
        'timeout_seconds' => 15,
        // Delay before retry n (§14.5): 5 s, 1 min, 5 min, 30 min, 2 h, 5 h, 10 h, 14 h.
        'backoff_seconds' => [5, 60, 300, 1800, 7200, 18000, 36000, 50400],
        'max_attempts' => 9,
        // An endpoint failing continuously for longer than this is disabled (`failing`).
        'disable_after_days' => 5,
        // Events (and their deliveries) are kept this long (API.md §4.4).
        'retention_days' => 30,
        // The scheduler dispatches outbox rows that are still undispatched after this delay.
        'dispatch_grace_seconds' => 30,
        // A claimed delivery is not picked up again by the scheduler before this lease expires.
        'lease_seconds' => 120,
        'response_excerpt_bytes' => 2048,
    ],

    'imports' => [
        'max_rows' => 10000,
        'preview_errors' => 100,
    ],

    // Import sources, import error files and exports are deleted after this many days.
    'files_retention_days' => 7,

];
