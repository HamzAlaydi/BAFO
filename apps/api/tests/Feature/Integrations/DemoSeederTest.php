<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Seeders\IntegrationsDemoSeeder;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Database\Seeders\DemoSeeder;
use Tests\Support\Integrations\IntegrationsFixtures;
use Tests\Support\Integrations\PassportKeys;

it('seeds a demo client, key, webhook endpoint and vendors for Issuer Co', function () {
    PassportKeys::load();
    $issuer = Organization::factory()->apiEnabled()->create(['cr_number' => DemoSeeder::ORGANIZATIONS['issuer']['cr_number']]);
    User::factory()->withMembership($issuer, OrgRole::Owner)->create(['email' => DemoSeeder::user('issuer.owner')['email']]);
    $supplierA = Organization::factory()->create(['cr_number' => DemoSeeder::ORGANIZATIONS['supplier_a']['cr_number']]);

    test()->seed(IntegrationsDemoSeeder::class);

    $client = ApiClient::query()->sole();

    expect($client->organization_id)->toBe($issuer->id)
        ->and(WebhookEndpoint::query()->sole()->url)->toBe(IntegrationsDemoSeeder::WEBHOOK_URL)
        ->and(WebhookEndpoint::query()->sole()->secret)->toBe(IntegrationsDemoSeeder::webhookSecret())
        ->and(Vendor::query()->where('organization_id', $issuer->id)->count())->toBe(4)
        ->and(Vendor::query()->where('cr_number', $supplierA->cr_number)->sole()->linked_organization_id)->toBe($supplierA->id);

    test()->getJson('/api/public/v1/client', IntegrationsFixtures::bearer(IntegrationsDemoSeeder::apiKey()))
        ->assertOk()
        ->assertJsonPath('data.client_id', $client->public_id);

    test()->postJson('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => IntegrationsDemoSeeder::CLIENT_SECRET,
    ])->assertOk();
});

it('skips when Issuer Co has not been seeded', function () {
    test()->seed(IntegrationsDemoSeeder::class);

    expect(ApiClient::query()->count())->toBe(0);
});
