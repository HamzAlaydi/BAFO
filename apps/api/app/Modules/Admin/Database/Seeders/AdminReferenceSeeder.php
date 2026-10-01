<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Seeders;

use App\Modules\Admin\Enums\AdminRole;
use App\Modules\Admin\Models\Admin;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * The platform super admin (ARCHITECTURE §17 step 4). Credentials come from ADMIN_SEED_EMAIL and
 * ADMIN_SEED_PASSWORD (`bafo.admin.seed`); when either is empty, local and testing environments
 * use the DemoSeeder credentials (also listed in docs/build/DEMO.md), and other environments
 * seed nothing.
 *
 * Idempotent: an admin that already exists with that e-mail is left as it is, so a password
 * changed in the panel survives a re-seed.
 */
final class AdminReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = self::credentials();

        if ($credentials === null) {
            Log::warning('AdminReferenceSeeder: ADMIN_SEED_EMAIL / ADMIN_SEED_PASSWORD are not set; no admin was seeded.');

            return;
        }

        Admin::query()->firstOrCreate(
            ['email' => mb_strtolower($credentials['email'])],
            [
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'role' => AdminRole::SuperAdmin,
                'is_active' => true,
            ],
        );
    }

    /**
     * @return array{email: string, password: string, name: string}|null
     */
    public static function credentials(): ?array
    {
        $email = config('bafo.admin.seed.email');
        $password = config('bafo.admin.seed.password');

        if (is_string($email) && $email !== '' && is_string($password) && $password !== '') {
            return ['email' => $email, 'password' => $password, 'name' => DemoSeeder::ADMIN_NAME];
        }

        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        return [
            'email' => DemoSeeder::ADMIN_EMAIL,
            'password' => DemoSeeder::ADMIN_PASSWORD,
            'name' => DemoSeeder::ADMIN_NAME,
        ];
    }
}
