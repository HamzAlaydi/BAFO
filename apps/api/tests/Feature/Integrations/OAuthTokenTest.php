<?php

declare(strict_types=1);

use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Http\Middleware\AuthenticatePublicApiClient;
use App\Modules\Integrations\Models\ApiClient;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use Tests\Support\Integrations\IntegrationsFixtures;
use Tests\Support\Integrations\PassportKeys;

beforeEach(function () {
    PassportKeys::load();
});

function integrationsTokenRequest(array $body = [], array $headers = []): TestResponse
{
    return test()->postJson('/api/public/v1/oauth/token', $body, $headers);
}

it('issues a client-credentials token in the RFC 6749 shape with HTTP Basic', function () {
    [$client, $secret] = IntegrationsFixtures::oauthClient(scopes: [ApiScope::VendorsRead, ApiScope::VendorsWrite]);

    $response = integrationsTokenRequest(
        ['grant_type' => 'client_credentials'],
        ['Authorization' => 'Basic '.base64_encode($client->public_id.':'.$secret)],
    )->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect(array_keys($response->json()))->toBe(['access_token', 'token_type', 'expires_in', 'scope'])
        ->and($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('expires_in'))->toBeGreaterThan(1700)->toBeLessThanOrEqual(1800)
        ->and($response->json('scope'))->toBe('vendors:read vendors:write')
        ->and($response->json('access_token'))->toBeString()->not->toBeEmpty();
});

it('accepts the credentials in a form-encoded body and narrows the scope', function () {
    [$client, $secret] = IntegrationsFixtures::oauthClient();

    $response = test()->post('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => strtoupper($client->public_id),
        'client_secret' => $secret,
        'scope' => 'vendors:read',
    ], ['Accept' => 'application/json'])->assertOk();

    expect($response->json('scope'))->toBe('vendors:read');

    test()->getJson('/api/public/v1/client', IntegrationsFixtures::bearer($response->json('access_token')))
        ->assertOk()
        ->assertJsonPath('data.auth', 'oauth')
        ->assertJsonPath('data.scopes', ['vendors:read'])
        ->assertJsonPath('data.client_id', $client->public_id);
});

it('lets the access token call the API until the client narrows its scopes', function () {
    [$client, $secret] = IntegrationsFixtures::oauthClient(scopes: [ApiScope::VendorsRead, ApiScope::WebhooksManage]);

    $token = integrationsTokenRequest(['grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $secret])
        ->json('access_token');

    test()->getJson('/api/public/v1/webhook-endpoints', IntegrationsFixtures::bearer($token))->assertOk();

    $client->forceFill(['scopes' => ['vendors:read']])->save();

    test()->getJson('/api/public/v1/webhook-endpoints', IntegrationsFixtures::bearer($token))
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope')
        ->assertJsonPath('details.required_scope', 'webhooks:manage');
});

it('rejects a grant other than client credentials', function (?string $grant) {
    [$client, $secret] = IntegrationsFixtures::oauthClient();

    integrationsTokenRequest(array_filter(['grant_type' => $grant, 'client_id' => $client->public_id, 'client_secret' => $secret]))
        ->assertStatus(400)
        ->assertJsonPath('error', 'unsupported_grant_type')
        ->assertJsonPath('code', 'unsupported_grant_type')
        ->assertJsonStructure(['error', 'error_description', 'message', 'code', 'errors']);
})->with(['password' => 'password', 'authorization_code' => 'authorization_code', 'missing' => null]);

it('rejects a scope outside the client scopes', function () {
    [$client, $secret] = IntegrationsFixtures::oauthClient(scopes: [ApiScope::VendorsRead]);

    integrationsTokenRequest(['grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $secret, 'scope' => 'vendors:read webhooks:manage'])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_scope')
        ->assertJsonPath('code', 'invalid_scope');
});

it('rejects client authentication failures with 401 invalid_client', function (Closure $setup) {
    [$client, $secret] = IntegrationsFixtures::oauthClient();
    [$clientId, $clientSecret] = $setup($client, $secret);

    integrationsTokenRequest(['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $clientSecret])
        ->assertUnauthorized()
        ->assertJsonPath('error', 'invalid_client')
        ->assertJsonPath('code', 'invalid_client');
})->with([
    'wrong secret' => [fn (ApiClient $client, string $secret) => [$client->public_id, 'not-the-secret']],
    'unknown client' => [fn (ApiClient $client, string $secret) => [strtolower((string) Str::ulid()), $secret]],
    'malformed client id' => [fn (ApiClient $client, string $secret) => ['nope', $secret]],
    'Passport client id instead of ours' => [fn (ApiClient $client, string $secret) => [$client->oauth_client_id, $secret]],
    'suspended client' => [function (ApiClient $client, string $secret) {
        $client->forceFill(['status' => 'suspended'])->save();

        return [$client->public_id, $secret];
    }],
    'revoked client' => [function (ApiClient $client, string $secret) {
        $client->forceFill(['status' => 'revoked'])->save();

        return [$client->public_id, $secret];
    }],
    'organization without api_enabled' => [function (ApiClient $client, string $secret) {
        $client->organization->forceFill(['api_enabled' => false])->save();

        return [$client->public_id, $secret];
    }],
    'suspended organization' => [function (ApiClient $client, string $secret) {
        $client->organization->forceFill(['status' => 'suspended'])->save();

        return [$client->public_id, $secret];
    }],
]);

it('answers the Basic challenge when HTTP Basic credentials fail', function () {
    [$client] = IntegrationsFixtures::oauthClient();

    integrationsTokenRequest(['grant_type' => 'client_credentials'], ['Authorization' => 'Basic '.base64_encode($client->public_id.':wrong')])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="BAFO API"');
});

it('rate limits the token endpoint per IP and client_id with the OAuth error shape', function () {
    [$client] = IntegrationsFixtures::oauthClient();
    $body = ['grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => 'wrong'];

    for ($i = 0; $i < 20; $i++) {
        integrationsTokenRequest($body)->assertUnauthorized();
    }

    integrationsTokenRequest($body)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error', 'too_many_requests')
        ->assertJsonPath('code', 'too_many_requests');
});

it('does not register the default Passport routes', function () {
    $names = collect(app(Router::class)->getRoutes()->getRoutes())->map(fn ($route) => $route->getName())->filter();

    expect($names->filter(fn (string $name) => str_starts_with($name, 'passport.'))->all())->toBe([]);
    test()->postJson('/oauth/token', ['grant_type' => 'client_credentials'])->assertNotFound();
});

it('declares the token endpoint without client auth or the public-api limiter', function () {
    $route = app(Router::class)->getRoutes()->getByName('public.v1.oauth.token');

    expect(app(Router::class)->gatherRouteMiddleware($route))
        ->toContain('Illuminate\Routing\Middleware\ThrottleRequests:oauth-token')
        ->not->toContain('Illuminate\Routing\Middleware\ThrottleRequests:public-api')
        ->not->toContain(AuthenticatePublicApiClient::class);
});
