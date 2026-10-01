<?php

declare(strict_types=1);

namespace App\Modules\Platform\Database\Seeders;

use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;

/**
 * Demo values of the Platform runtime settings (ARCHITECTURE §15.3), run by DemoSeeder (never in
 * production). `GET /app-config` then carries store links and support contacts, so the mobile
 * update screen (M03), the account gate and the help page (M60) show their buttons and cards in
 * a local demo.
 *
 * These are PLACEHOLDERS, not real contacts: the links and the e-mail use the reserved
 * `bafo.example` domain (RFC 2606), and the phone numbers are dummies. Real values are entered
 * in the admin settings page before release. Idempotent: it overwrites only these two settings.
 */
final class PlatformDemoSeeder extends Seeder
{
    /**
     * `app.store_links`: placeholder store pages.
     *
     * @var array{ios: string, android: string}
     */
    public const array STORE_LINKS = [
        'ios' => 'https://bafo.example/placeholder/app-store',
        'android' => 'https://bafo.example/placeholder/google-play',
    ];

    /**
     * `app.support`: placeholder contacts.
     *
     * @var array{email: string, phone: string, whatsapp: string}
     */
    public const array SUPPORT = [
        'email' => 'support@bafo.example',
        'phone' => '+966500000000',
        'whatsapp' => '+966500000000',
    ];

    public function run(Settings $settings): void
    {
        $settings->set('app.store_links', self::STORE_LINKS, null);
        $settings->set('app.support', self::SUPPORT, null);
    }
}
