<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Integrations\Actions\ApiClients\SetApiClientSuspension;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\Token;
use Tests\Support\Integrations\IntegrationsFixtures;
use Tests\Support\Integrations\PassportKeys;

beforeEach(function () {
    PassportKeys::load();
});

const INTEGRATIONS_CLIENTS = '/api/app/v1/integrations/api-clients';

it('creates a client with a Passport client and shows the secret once', function () {
    [$organization, $user] = IntegrationsFixtures::signIn();

    $response = test()->postJson(INTEGRATIONS_CLIENTS, [
        'name' => 'SAP CPI – PROD',
        'description' => 'Integration flow',
        'scopes' => ['vendors:write', 'competitions:read', 'vendors:write'],
    ])->assertStatus(422);

    $response = test()->postJson(INTEGRATIONS_CLIENTS, [
        'name' => 'SAP CPI – PROD',
        'description' => 'Integration flow',
        'scopes' => ['vendors:write', 'competitions:read'],
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => [
            'id', 'client_id', 'name', 'description', 'scopes', 'status', 'keys', 'last_used_at', 'created_by' => ['id', 'name'],
            'created_at', 'client_secret',
        ], 'meta' => ['server_time']]);

    $client = ApiClient::query()->sole();

    expect($response->json('data.id'))->toBe($client->public_id)
        ->and($response->json('data.client_id'))->toBe($client->public_id)
        ->and($response->json('data.scopes'))->toBe(['vendors:write', 'competitions:read'])
        ->and($response->json('data.created_by.id'))->toBe($user->public_id)
        ->and($client->organization_id)->toBe($organization->id)
        ->and(PassportClient::query()->find($client->oauth_client_id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'api_client.created')->count())->toBe(1);

    // The secret works for the token endpoint and is never shown again.
    test()->postJson('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $response->json('data.client_secret'),
    ])->assertOk();

    test()->getJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id)->assertOk()->assertJsonMissingPath('data.client_secret');
    test()->getJson(INTEGRATIONS_CLIENTS)->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.client_secret');
});

it('validates the client input', function () {
    IntegrationsFixtures::signIn();

    test()->postJson(INTEGRATIONS_CLIENTS, ['name' => str_repeat('a', 121), 'scopes' => ['vendors:delete']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['name', 'scopes.0']);

    test()->postJson(INTEGRATIONS_CLIENTS, ['name' => 'ERP'])->assertJsonValidationErrors(['scopes']);
});

it('updates the name, description and scopes', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $client = ApiClient::factory()->for($organization)->create();

    test()->patchJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id, ['description' => null, 'scopes' => ['offers:read']])
        ->assertOk()
        ->assertJsonPath('data.scopes', ['offers:read'])
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.name', $client->name);

    expect(AuditLog::query()->where('action', 'api_client.updated')->sole()->changes)->toHaveKey('scopes');
});

it('creates a key shown once and lists it masked', function () {
    [$organization] = IntegrationsFixtures::signIn();
    [$client] = IntegrationsFixtures::oauthClient($organization);

    $response = test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/keys', ['expires_in_days' => 30])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'prefix', 'masked', 'expires_at', 'revoked_at', 'last_used_at', 'created_at', 'key']]);

    $plain = $response->json('data.key');
    $key = ApiKey::query()->sole();

    expect($plain)->toMatch('/^bafo_test_[a-z0-9]{8}_[A-Za-z0-9]{32}$/')
        ->and($key->prefix)->toBe(substr($plain, 10, 8))
        ->and($key->key_hash)->toBe(hash('sha256', $plain))
        ->and($key->last_four)->toBe(substr($plain, -4))
        ->and($key->expires_at?->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($response->json('data.masked'))->toBe('bafo_test_'.$key->prefix.'_…'.$key->last_four);

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertOk();

    test()->getJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id)
        ->assertOk()
        ->assertJsonPath('data.keys.0.id', $key->public_id)
        ->assertJsonMissingPath('data.keys.0.key');
});

it('defaults keys to 365 days and bounds them to 730', function () {
    [$organization] = IntegrationsFixtures::signIn();
    $client = ApiClient::factory()->for($organization)->create();

    test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/keys')->assertCreated();
    expect(ApiKey::query()->sole()->expires_at?->toDateString())->toBe(now()->addDays(365)->toDateString());

    test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/keys', ['expires_in_days' => 731])
        ->assertJsonValidationErrors(['expires_in_days']);
    test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/keys', ['expires_in_days' => 0])
        ->assertJsonValidationErrors(['expires_in_days']);
});

it('revokes a key', function () {
    [$organization] = IntegrationsFixtures::signIn();
    [$plain, $client, $key] = IntegrationsFixtures::apiKey(organization: $organization);

    test()->deleteJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/keys/'.$key->public_id)->assertNoContent();

    expect($key->refresh()->revoked_at)->not->toBeNull();
    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertUnauthorized();
});

it('does not revoke a key through another client', function () {
    [$organization] = IntegrationsFixtures::signIn();
    [, , $key] = IntegrationsFixtures::apiKey(organization: $organization);
    $other = ApiClient::factory()->for($organization)->create();

    test()->deleteJson(INTEGRATIONS_CLIENTS.'/'.$other->public_id.'/keys/'.$key->public_id)->assertNotFound();

    expect($key->refresh()->revoked_at)->toBeNull();
});

it('rotates the client secret', function () {
    [$organization] = IntegrationsFixtures::signIn();
    [$client, $oldSecret] = IntegrationsFixtures::oauthClient($organization);

    $newSecret = test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/rotate-secret')
        ->assertOk()
        ->json('data.client_secret');

    expect($newSecret)->toBeString()->not->toBe($oldSecret);

    $token = fn (string $secret) => test()->postJson('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $secret,
    ]);

    $token($oldSecret)->assertUnauthorized();
    $token($newSecret)->assertOk();
    expect(AuditLog::query()->where('action', 'api_client.secret_rotated')->count())->toBe(1);
});

it('revokes a client: its tokens, its Passport client and every key', function () {
    [$organization] = IntegrationsFixtures::signIn();
    [$client, $secret] = IntegrationsFixtures::oauthClient($organization);
    [$plain] = IntegrationsFixtures::apiKey($client);
    $token = test()->postJson('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $secret,
    ])->json('access_token');

    test()->deleteJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id)->assertNoContent();

    $client->refresh();

    expect($client->status)->toBe(ApiClientStatus::Revoked)
        ->and($client->revoked_at)->not->toBeNull()
        ->and(PassportClient::query()->find($client->oauth_client_id)?->revoked)->toBeTrue()
        ->and(Token::query()->where('client_id', $client->oauth_client_id)->where('revoked', false)->count())->toBe(0)
        ->and(ApiKey::query()->whereNull('revoked_at')->count())->toBe(0);

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($token))->assertUnauthorized();
    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertUnauthorized();

    test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/keys')->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');
    test()->postJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id.'/rotate-secret')->assertStatus(409);
});

it('hides clients of other organizations', function (string $method, string $suffix) {
    IntegrationsFixtures::signIn();
    $foreign = ApiClient::factory()->create();

    test()->json($method, INTEGRATIONS_CLIENTS.'/'.$foreign->public_id.$suffix)->assertNotFound();
})->with([
    ['GET', ''], ['PATCH', ''], ['DELETE', ''], ['POST', '/rotate-secret'], ['POST', '/keys'],
]);

it('lists only the organization clients', function () {
    [$organization] = IntegrationsFixtures::signIn();
    ApiClient::factory()->for($organization)->count(2)->create();
    ApiClient::factory()->create();

    test()->getJson(INTEGRATIONS_CLIENTS)->assertOk()->assertJsonCount(2, 'data');
});

it('requires integrations.manage', function () {
    [$organization] = IntegrationsFixtures::signIn(OrgRole::Member);
    $client = ApiClient::factory()->for($organization)->create();

    test()->getJson(INTEGRATIONS_CLIENTS)->assertForbidden()->assertJsonPath('code', 'forbidden');
    test()->getJson(INTEGRATIONS_CLIENTS.'/'.$client->public_id)->assertForbidden();
    test()->postJson(INTEGRATIONS_CLIENTS, ['name' => 'x', 'scopes' => ['vendors:read']])->assertForbidden();
});

it('requires the organization api_enabled flag', function () {
    IntegrationsFixtures::signIn(apiEnabled: false);

    test()->getJson(INTEGRATIONS_CLIENTS)->assertForbidden()->assertJsonPath('code', 'api_access_disabled');
    test()->getJson('/api/app/v1/integrations/webhook-endpoints')->assertForbidden()->assertJsonPath('code', 'api_access_disabled');
});

it('requires a signed-in user', function () {
    test()->getJson(INTEGRATIONS_CLIENTS)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('suspends and reactivates a client for the platform admin', function () {
    [$plain, $client] = IntegrationsFixtures::apiKey();
    $suspend = app(SetApiClientSuspension::class);

    $suspend->handle($client, true, Actor::system());
    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertUnauthorized();

    $suspend->handle($client, false, Actor::system());
    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertOk();

    expect(AuditLog::query()->pluck('action')->all())->toContain('api_client.suspended', 'api_client.reactivated');
});
