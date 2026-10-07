<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Platform module configuration: config('bafo.platform.*')
|--------------------------------------------------------------------------
|
| Environment keys: ARCHITECTURE §15.2. Runtime settings: ARCHITECTURE §15.3.
|
*/

return [

    // Web origin used for links in e-mails.
    'web_url' => env('WEB_URL', 'http://localhost:3000'),

    'pdf' => [
        // PdfRenderer driver (ARCHITECTURE §4.11): mpdf.
        'driver' => env('PDF_DRIVER', 'mpdf'),
    ],

    // Realtime connection handed to clients by GET /app-config (ARCHITECTURE §9.1).
    // The Android dev flavour overrides the host with 10.0.2.2.
    'realtime' => [
        'key' => env('REVERB_APP_KEY', ''),
        'host' => env('REVERB_PUBLIC_HOST', 'localhost'),
        'port' => (int) env('REVERB_PORT', 8085),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],

    // Defaults of the Platform runtime settings (ARCHITECTURE §15.3). They are admin-editable
    // values in app_settings, not environment keys: PlatformServiceProvider registers them with
    // Settings::defaults(); read them with app(Settings::class)->get($key).
    'settings' => [
        'app.min_version.ios' => '1.0.0',
        'app.min_version.android' => '1.0.0',
        'app.latest_version.ios' => '1.0.0',
        'app.latest_version.android' => '1.0.0',
        'app.store_links' => ['ios' => '', 'android' => ''],
        'app.maintenance.enabled' => false,
        'app.maintenance.message' => ['ar' => '', 'en' => ''],
        'app.support' => ['email' => '', 'phone' => '', 'whatsapp' => ''],
        // Release scope (docs/build/RELEASE_SCOPE.md §1.1): `core` hides the advanced features
        // behind App\Support\Features\FeatureFlags; `full` brings everything back. Default `core`
        // in every environment; tests/TestCase.php runs the suite as `full`.
        // Flip it from the admin settings page or `php artisan platform:release-scope full`.
        'platform.release_scope' => 'core',
    ],

];
