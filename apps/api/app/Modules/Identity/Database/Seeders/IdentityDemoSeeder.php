<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\ConsentRecorder;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Support\Auth\Actor;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The demo organizations and users of ARCHITECTURE §17 (`DemoSeeder::ORGANIZATIONS`): verified
 * e-mails, active memberships with the listed roles and flags, the organization feature flags,
 * complete billing profiles and the terms and privacy consents. Password: `DemoSeeder::PASSWORD`.
 * Subscriptions are Billing's (BillingDemoSeeder).
 */
final class IdentityDemoSeeder extends Seeder
{
    /**
     * City of the demo data => region code (CatalogReferenceSeeder).
     *
     * @var array<string, string>
     */
    private const array CITY_REGIONS = [
        'الرياض' => 'RIY',
        'جدة' => 'MAK',
        'مكة المكرمة' => 'MAK',
        'الدمام' => 'EAS',
        'الخبر' => 'EAS',
    ];

    /**
     * Category codes per demo organization.
     *
     * @var array<string, list<string>>
     */
    private const array CATEGORIES = [
        'issuer' => ['it_hardware', 'office_supplies', 'surplus_scrap'],
        'supplier_a' => ['it_hardware', 'software_services'],
        'supplier_b' => ['it_hardware', 'office_supplies'],
        'supplier_c' => ['logistics', 'facility_management'],
        'buyer_d' => ['surplus_scrap', 'industrial_equipment'],
    ];

    public function __construct(private readonly ConsentRecorder $consents) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $index = 0;

            foreach (DemoSeeder::ORGANIZATIONS as $key => $definition) {
                $index++;
                $this->seedOrganization($key, $definition, $index);
            }
        });
    }

    /**
     * @param  array{name: string, legal_name_ar: string, cr_number: string, city: string, plan: string|null, flags: array<string, bool>, users: array<string, array{name: string, email: string, role: string, can_award: bool, can_purchase: bool}>}  $definition
     */
    private function seedOrganization(string $key, array $definition, int $index): void
    {
        $owner = $definition['users']['owner'];
        $regionId = Region::query()->where('code', self::CITY_REGIONS[$definition['city']] ?? 'RIY')->value('id');
        $now = Date::now();

        $organization = Organization::factory()->create([
            'name' => $definition['name'],
            'legal_name_ar' => $definition['legal_name_ar'],
            'legal_name_en' => $definition['name'].' Co. Ltd.',
            'cr_number' => $definition['cr_number'],
            'vat_registered' => true,
            'vat_number' => '3'.str_pad((string) $index, 13, '0', STR_PAD_LEFT).'3',
            'region_id' => $regionId,
            'city' => $definition['city'],
            'website' => null,
            'email' => $owner['email'],
            'phone' => '+96650000000'.$index,
            'status' => OrganizationStatus::Active,
            'verified_at' => $now,
            'api_enabled' => $definition['flags']['api_enabled'] ?? false,
            'auction_enabled' => $definition['flags']['auction_enabled'] ?? false,
            'sponsorship_enabled' => $definition['flags']['sponsorship_enabled'] ?? false,
        ]);

        $organization->categories()->sync(
            Category::query()->whereIn('code', self::CATEGORIES[$key] ?? [])->pluck('id')->all(),
        );

        $ownerUser = null;
        $userIndex = 0;

        foreach ($definition['users'] as $user) {
            $userIndex++;

            $model = User::factory()->create([
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => '+9665'.str_pad((string) ($index * 10 + $userIndex), 8, '0', STR_PAD_LEFT),
                'password' => DemoSeeder::PASSWORD,
                'email_verified_at' => $now,
                'locale' => 'ar',
                'status' => UserStatus::Active,
            ]);

            $role = OrgRole::from($user['role']);

            Membership::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $model->id,
                'role' => $role,
                'can_award' => $user['can_award'],
                'can_purchase' => $user['can_purchase'],
                'status' => MembershipStatus::Active,
                'invited_by_user_id' => $role === OrgRole::Owner ? null : $ownerUser?->id,
                'joined_at' => $now,
            ]);

            $ownerUser ??= $model;

            $this->consents->record(
                $model,
                $organization->id,
                [LegalDocumentCode::Terms, LegalDocumentCode::Privacy],
                'ar',
                Actor::system(),
            );
        }
    }
}
