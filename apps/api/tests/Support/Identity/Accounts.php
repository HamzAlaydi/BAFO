<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use App\Modules\Catalog\Database\Seeders\CatalogReferenceSeeder;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use Illuminate\Support\Facades\Auth;

/**
 * Identity test data: organizations with members, real Sanctum bearer tokens, a valid
 * registration payload and the published legal documents that consents point at.
 */
final class Accounts
{
    public const string PASSWORD = 'Passw0rd!';

    /**
     * A strong password that satisfies the §13.9 rule.
     */
    public const string NEW_PASSWORD = 'N3w-Passw0rd!';

    /**
     * An organization with an active, verified owner (password PASSWORD).
     */
    public static function owner(?Organization $organization = null): User
    {
        return self::member($organization ?? Organization::factory()->create(), OrgRole::Owner);
    }

    public static function member(Organization $organization, OrgRole $role = OrgRole::Member, array $attributes = []): User
    {
        return User::factory()
            ->withMembership($organization, $role)
            ->create(['password' => self::PASSWORD, ...$attributes])
            ->load('membership.organization');
    }

    /**
     * A real personal access token, as the apps send it (never Sanctum::actingAs), so the
     * account gate and token revocation are exercised.
     *
     * @return array<string, string>
     */
    public static function headers(User $user, string $device = 'Pixel 8 · android'): array
    {
        // The sanctum guard caches the first user it resolves within a test.
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken($device)->plainTextToken];
    }

    /**
     * @return array<string, string>
     */
    public static function bearer(string $token): array
    {
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }

    public static function seedCatalog(): void
    {
        (new CatalogReferenceSeeder)->run();
    }

    /**
     * Published terms and privacy documents (AR and EN), version 2026-10-01.
     */
    public static function publishLegalDocuments(): void
    {
        foreach ([LegalDocumentCode::Terms, LegalDocumentCode::Privacy] as $code) {
            foreach (['ar', 'en'] as $locale) {
                LegalDocument::query()->create([
                    'code' => $code,
                    'locale' => $locale,
                    'version' => '2026-10-01',
                    'title' => $code->value.' '.$locale,
                    'body_markdown' => 'Draft text',
                    'published_at' => now()->subDay(),
                ]);
            }
        }
    }

    /**
     * A valid `POST /auth/register` body (needs the Catalog seeded).
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $organization
     * @return array<string, mixed>
     */
    public static function registration(array $overrides = [], array $organization = []): array
    {
        $region = Region::query()->where('code', 'RIY')->firstOrFail();
        $category = Category::query()->where('code', 'it_hardware')->firstOrFail();

        return [
            'name' => 'سارة العتيبي',
            'email' => 'Owner@Acme.SA',
            'phone' => '+966501234567',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'locale' => 'ar',
            'organization' => [
                'name' => 'شركة المصدر',
                'cr_number' => '1010123456',
                'region_id' => strtoupper($region->public_id),
                'city' => 'الرياض',
                'vat_registered' => true,
                'vat_number' => '300000000000003',
                'legal_name_ar' => 'شركة المصدر للتجارة',
                'legal_name_en' => 'Al Masdar Trading Co.',
                'website' => 'https://masdar.sa',
                'national_address' => [
                    'building_number' => '1234',
                    'street' => 'طريق الملك فهد',
                    'district' => 'العليا',
                    'postal_code' => '12345',
                    'additional_number' => '5678',
                    'short_address' => 'RRRD2929',
                ],
                'category_ids' => [$category->public_id],
                'visible_in_suggestions' => true,
                ...$organization,
            ],
            'accept_terms' => true,
            'accept_privacy' => true,
            ...$overrides,
        ];
    }
}
