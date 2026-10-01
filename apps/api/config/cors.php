<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Accept-Language',
        'Idempotency-Key',
        'If-None-Match',
        'X-Platform',
        'X-App-Version',
        'X-Request-Id',
        'X-Requested-With',
        'X-Socket-Id',
    ],

    // Response headers browser clients read (API.md §0): ETag (lookups revalidation with
    // If-None-Match), Retry-After (429), Idempotent-Replayed (replayed offers), Content-Disposition
    // (file names of downloads) and X-Request-Id (support references).
    'exposed_headers' => [
        'ETag',
        'Retry-After',
        'Idempotent-Replayed',
        'Content-Disposition',
        'X-Request-Id',
        'Content-Language',
    ],

    'max_age' => 0,

    'supports_credentials' => false,

];
