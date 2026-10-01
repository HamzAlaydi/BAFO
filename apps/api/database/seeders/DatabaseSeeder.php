<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan db:seed` (ARCHITECTURE §17): runs every module's
 * App\Modules\<Module>\Database\Seeders\<Module>ReferenceSeeder that exists, in module
 * (foreign key) order. Reference seeders are idempotent, so this is safe to re-run.
 *
 * Demo data is separate: `php artisan db:seed --class=DemoSeeder` (scripts/reset-db.sh runs
 * both).
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Module order = migration order (docs/build/ARCHITECTURE.md §3.5).
     *
     * @var list<string>
     */
    public const array MODULES = [
        'Platform',
        'Catalog',
        'Identity',
        'Integrations',
        'Competitions',
        'Bidding',
        'Billing',
        'Notifications',
        'Admin',
    ];

    public function run(): void
    {
        foreach (self::referenceSeeders() as $seeder) {
            $this->call($seeder);
        }
    }

    /**
     * The reference seeders that exist today, in module order.
     *
     * @return list<class-string<Seeder>>
     */
    public static function referenceSeeders(): array
    {
        $seeders = [];

        foreach (self::MODULES as $module) {
            $seeder = "App\\Modules\\{$module}\\Database\\Seeders\\{$module}ReferenceSeeder";

            if (class_exists($seeder) && is_subclass_of($seeder, Seeder::class)) {
                $seeders[] = $seeder;
            }
        }

        return $seeders;
    }
}
