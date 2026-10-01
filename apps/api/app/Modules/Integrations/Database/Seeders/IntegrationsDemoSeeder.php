<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Database\Seeders;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Actions\ApiClients\CreateApiClient;
use App\Modules\Integrations\Actions\Vendors\CreateVendor;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client as PassportClient;

/**
 * Integrations demo data (ARCHITECTURE §17, docs/build/DEMO.md), for Issuer Co:
 *
 *   - one API client ("ERP demo") with every scope, a known client secret and a known API key;
 *   - one webhook endpoint at http://localhost:9999/webhooks subscribed to every event, with a
 *     known signing secret (private targets are allowed locally, §14.6);
 *   - four vendors: Supplier A, B and C (linked to their BAFO organizations, with SAP supplier
 *     keys) and one company that is not on BAFO.
 *
 * The credentials are demo values for local and demo environments only (DemoSeeder refuses
 * production). The organization is found by CR number; when Identity has not seeded it the
 * seeder is skipped with a warning.
 */
final class IntegrationsDemoSeeder extends Seeder
{
    public const string CLIENT_NAME = 'ERP demo';

    public const string CLIENT_SECRET = 'bafo-demo-client-secret-0000000000000001';

    /** The key suffix: `bafo_{env}_demo0001_` + this. */
    public const string API_KEY_SECRET = 'DemoKey0000000000000000000000001';

    public const string KEY_PREFIX = 'demo0001';

    public const string WEBHOOK_URL = 'http://localhost:9999/webhooks';

    /** `whsec_` + base64 of these 32 bytes. */
    public const string WEBHOOK_SECRET_BYTES = 'bafo-demo-webhook-secret-32bytes';

    public static function apiKey(): string
    {
        return 'bafo_'.ApiKeyGenerator::environment().'_'.self::KEY_PREFIX.'_'.self::API_KEY_SECRET;
    }

    public static function webhookSecret(): string
    {
        return 'whsec_'.base64_encode(self::WEBHOOK_SECRET_BYTES);
    }

    public function run(CreateApiClient $createClient, CreateVendor $createVendor): void
    {
        $issuer = Organization::query()->where('cr_number', DemoSeeder::ORGANIZATIONS['issuer']['cr_number'])->first();
        $owner = User::query()->with('membership')->where('email', DemoSeeder::user('issuer.owner')['email'])->first();

        if ($issuer === null || $owner === null) {
            Log::warning('IntegrationsDemoSeeder: Issuer Co not found (run IdentityDemoSeeder first); skipped.');

            return;
        }

        $actor = Actor::forUser($owner);

        $issued = $createClient->handle($issuer, [
            'name' => self::CLIENT_NAME,
            'description' => 'Demo ERP integration (local only)',
            'scopes' => array_map(static fn (ApiScope $scope): string => $scope->value, ApiScope::cases()),
        ], $actor);

        PassportClient::query()->findOrFail($issued->client->oauth_client_id)
            ->forceFill(['secret' => self::CLIENT_SECRET])
            ->save();

        ApiKey::query()->create([
            'api_client_id' => $issued->client->id,
            'prefix' => self::KEY_PREFIX,
            'key_hash' => hash('sha256', self::apiKey()),
            'last_four' => substr(self::apiKey(), -4),
            'expires_at' => CarbonImmutable::now()->addDays(365),
            'created_by_user_id' => $owner->id,
        ]);

        WebhookEndpoint::query()->create([
            'organization_id' => $issuer->id,
            'url' => self::WEBHOOK_URL,
            'description' => 'Local webhook receiver (demo)',
            'event_types' => [WebhookEventType::WILDCARD],
            'secret' => self::webhookSecret(),
            'status' => WebhookEndpointStatus::Active,
            'created_by_user_id' => $owner->id,
        ]);

        $this->seedVendors($issuer, $createVendor, $actor);
    }

    private function seedVendors(Organization $issuer, CreateVendor $createVendor, Actor $actor): void
    {
        $number = 100045;

        foreach (['supplier_a', 'supplier_b', 'supplier_c'] as $key) {
            $definition = DemoSeeder::ORGANIZATIONS[$key];

            $createVendor->handle($issuer, [
                'name' => $definition['legal_name_ar'],
                'name_en' => $definition['name'],
                'email' => DemoSeeder::user($key.'.owner')['email'],
                'cr_number' => $definition['cr_number'],
                'city' => $definition['city'],
                'external_refs' => [['system' => 'sap_s4', 'type' => 'supplier', 'id' => (string) $number++]],
            ], VendorSource::Web, $actor);
        }

        $createVendor->handle($issuer, [
            'name' => 'مؤسسة الأفق للتوريدات',
            'name_en' => 'Al Ofoq Supplies',
            'email' => 'sales@ofoq.demo.bafo.test',
            'city' => 'الرياض',
            'external_refs' => [['system' => 'sap_s4', 'type' => 'supplier', 'id' => (string) $number]],
        ], VendorSource::Import, $actor);
    }
}
