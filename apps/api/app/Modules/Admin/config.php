<?php

declare(strict_types=1);

/*
| Admin module configuration, merged into config('bafo.admin') (ARCHITECTURE §8.7, §15.2, §16, §17).
*/

return [
    // Filament app authentication (TOTP) is required for every admin when true. Default: off
    // locally, on in production (§8.7).
    'mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', env('APP_ENV') === 'production'),

    // AdminReferenceSeeder (§17): the super_admin it creates. When either value is empty, the
    // seeder falls back to the DemoSeeder credentials in local and testing only.
    'seed' => [
        'email' => env('ADMIN_SEED_EMAIL'),
        'password' => env('ADMIN_SEED_PASSWORD'),
    ],

    // The panel is Arabic (RTL) by default, with an English toggle (§16).
    'locales' => ['ar', 'en'],
    'default_locale' => 'ar',
];
