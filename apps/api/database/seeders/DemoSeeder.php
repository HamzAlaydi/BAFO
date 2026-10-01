<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use InvalidArgumentException;
use RuntimeException;

/**
 * Demo data (ARCHITECTURE §17): `php artisan db:seed --class=DemoSeeder`, after
 * `php artisan migrate:fresh --seed`. Refused in production.
 *
 * This class is the single source of the demo scenario and its credentials (also listed in
 * docs/build/DEMO.md). It runs the reference seeders (idempotent), then every module's
 * App\Modules\<Module>\Database\Seeders\<Module>DemoSeeder that exists. Module demo seeders
 * read the organizations, users and credentials from the constants below and build their data
 * through their factories and Actions (state machines included), e.g.
 *
 *     $owner = DemoSeeder::user('issuer.owner');   // ['name' => …, 'email' => …, 'role' => 'owner', …]
 *     Hash::make(DemoSeeder::PASSWORD);
 *
 * Demo seeders are not idempotent: run them on a freshly migrated database.
 */
final class DemoSeeder extends Seeder
{
    /**
     * Password of every demo user (local and demo environments only).
     */
    public const string PASSWORD = 'Bafo-Demo-2026';

    /**
     * The platform super_admin in local, when ADMIN_SEED_EMAIL / ADMIN_SEED_PASSWORD are empty
     * (AdminReferenceSeeder).
     */
    public const string ADMIN_EMAIL = 'admin@demo.bafo.test';

    public const string ADMIN_PASSWORD = 'Bafo-Admin-2026';

    public const string ADMIN_NAME = 'BAFO Admin';

    /**
     * The demo organizations of §17. `plan` is a Billing plan code (`trial` = the trial on the
     * `billing.trial_plan_code` plan; null = no subscription). `flags` are organization columns.
     *
     * @var array<string, array{name: string, legal_name_ar: string, cr_number: string, city: string, plan: string|null, flags: array<string, bool>, users: array<string, array{name: string, email: string, role: string, can_award: bool, can_purchase: bool}>}>
     */
    public const array ORGANIZATIONS = [
        'issuer' => [
            'name' => 'Issuer Co',
            'legal_name_ar' => 'شركة الطارح التجريبية',
            'cr_number' => '1010000001',
            'city' => 'الرياض',
            'plan' => 'pro',
            'flags' => ['api_enabled' => true, 'auction_enabled' => true, 'sponsorship_enabled' => true],
            'users' => [
                'owner' => ['name' => 'سارة المالكة', 'email' => 'issuer.owner@demo.bafo.test', 'role' => 'owner', 'can_award' => true, 'can_purchase' => true],
                'admin' => ['name' => 'خالد المدير', 'email' => 'issuer.admin@demo.bafo.test', 'role' => 'admin', 'can_award' => true, 'can_purchase' => false],
                'member' => ['name' => 'نورة العضو', 'email' => 'issuer.member@demo.bafo.test', 'role' => 'member', 'can_award' => false, 'can_purchase' => false],
            ],
        ],
        'supplier_a' => [
            'name' => 'Supplier A',
            'legal_name_ar' => 'مورد أ التجريبي',
            'cr_number' => '1010000002',
            'city' => 'جدة',
            'plan' => 'single',
            'flags' => [],
            'users' => [
                'owner' => ['name' => 'أحمد مورد أ', 'email' => 'supplier-a.owner@demo.bafo.test', 'role' => 'owner', 'can_award' => true, 'can_purchase' => true],
            ],
        ],
        'supplier_b' => [
            'name' => 'Supplier B',
            'legal_name_ar' => 'مورد ب التجريبي',
            'cr_number' => '1010000003',
            'city' => 'الدمام',
            'plan' => null,
            'flags' => [],
            'users' => [
                'owner' => ['name' => 'فهد مورد ب', 'email' => 'supplier-b.owner@demo.bafo.test', 'role' => 'owner', 'can_award' => true, 'can_purchase' => true],
            ],
        ],
        'supplier_c' => [
            'name' => 'Supplier C',
            'legal_name_ar' => 'مورد ج التجريبي',
            'cr_number' => '1010000004',
            'city' => 'مكة المكرمة',
            'plan' => 'trial',
            'flags' => [],
            'users' => [
                'owner' => ['name' => 'ريم مورد ج', 'email' => 'supplier-c.owner@demo.bafo.test', 'role' => 'owner', 'can_award' => true, 'can_purchase' => true],
            ],
        ],
        'buyer_d' => [
            'name' => 'Buyer D',
            'legal_name_ar' => 'المشتري د التجريبي',
            'cr_number' => '1010000005',
            'city' => 'الخبر',
            'plan' => 'plus',
            'flags' => [],
            'users' => [
                'owner' => ['name' => 'ماجد المشتري د', 'email' => 'buyer-d.owner@demo.bafo.test', 'role' => 'owner', 'can_award' => true, 'can_purchase' => true],
            ],
        ],
    ];

    /**
     * Demo seeders run in dependency order, which differs from the migration order: issuers
     * need their subscriptions (Billing) before they publish competitions, and the sponsored
     * tender needs Billing's passes.
     *
     * CONTRACT-GAP: §17 lists the scenario, not the order of the module demo seeders.
     *
     * @var list<string>
     */
    public const array MODULES = [
        'Platform',
        'Catalog',
        'Identity',
        'Billing',
        'Integrations',
        'Competitions',
        'Bidding',
        'Notifications',
        'Admin',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder refuses to run in production.');
        }

        $this->call(DatabaseSeeder::class);

        foreach (self::demoSeeders() as $seeder) {
            $this->call($seeder);
        }
    }

    /**
     * The module demo seeders that exist today, in dependency order.
     *
     * @return list<class-string<Seeder>>
     */
    public static function demoSeeders(): array
    {
        $seeders = [];

        foreach (self::MODULES as $module) {
            $seeder = "App\\Modules\\{$module}\\Database\\Seeders\\{$module}DemoSeeder";

            if (class_exists($seeder) && is_subclass_of($seeder, Seeder::class)) {
                $seeders[] = $seeder;
            }
        }

        return $seeders;
    }

    /**
     * A demo user by "<organization>.<user>" key, e.g. "issuer.owner", "supplier_b.owner".
     *
     * @return array{name: string, email: string, role: string, can_award: bool, can_purchase: bool, organization: string}
     */
    public static function user(string $key): array
    {
        [$organization, $user] = array_pad(explode('.', $key, 2), 2, '');
        $definition = self::ORGANIZATIONS[$organization]['users'][$user] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown demo user [{$key}].");
        }

        return [...$definition, 'organization' => $organization];
    }
}
