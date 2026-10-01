<?php

declare(strict_types=1);

use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Http\Middleware\AuthenticatePublicApiClient;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Audit\AuditLog;
use App\Support\Auth\ActorType;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Token;
use Tests\Support\Integrations\IntegrationsFixtures;
use Tests\Support\Integrations\PassportKeys;

beforeEach(function () {
    PassportKeys::load();
});

it('authenticates an API key on ping and client', function () {
    [$plain, $client] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead]);

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))
        ->assertOk()
        ->assertJsonPath('data.pong', true)
        ->assertJsonMissingPath('meta.server_time');

    expect(test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->json('data.server_time'))->toBeIso8601Utc();

    test()->getJson('/api/public/v1/client', IntegrationsFixtures::bearer($plain))
        ->assertOk()
        ->assertExactJson(['data' => [
            'client_id' => $client->public_id,
            'name' => $client->name,
            'organization_id' => $client->organization->public_id,
            'scopes' => ['vendors:read'],
            'auth' => 'api_key',
            'environment' => 'test',
            'rate_limits' => ['per_minute_read' => 600, 'per_minute_write' => 300],
        ], 'meta' => []]);
});

it('refuses missing, malformed, unknown, revoked and expired credentials with invalid_token', function (Closure $token) {
    test()->getJson('/api/public/v1/ping', ($bearer = $token()) === null ? [] : IntegrationsFixtures::bearer($bearer))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_token')
        ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token"');
})->with([
    'no header' => [fn () => null],
    'random bearer' => [fn () => 'not-a-token'],
    'unknown key' => [fn () => 'bafo_test_abcd1234_'.str_repeat('A', 32)],
    'wrong secret of a known prefix' => [function () {
        [$plain] = IntegrationsFixtures::apiKey();

        return substr($plain, 0, -4).'ZZZZ';
    }],
    'revoked key' => [function () {
        [$plain, , $key] = IntegrationsFixtures::apiKey();
        $key->forceFill(['revoked_at' => now()])->save();

        return $plain;
    }],
    'expired key' => [function () {
        [$plain, , $key] = IntegrationsFixtures::apiKey();
        $key->forceFill(['expires_at' => now()->subMinute()])->save();

        return $plain;
    }],
    'key of a revoked client' => [function () {
        [$plain, $client] = IntegrationsFixtures::apiKey();
        $client->forceFill(['status' => 'revoked'])->save();

        return $plain;
    }],
    'key of a suspended client' => [function () {
        [$plain, $client] = IntegrationsFixtures::apiKey();
        $client->forceFill(['status' => 'suspended'])->save();

        return $plain;
    }],
    'live key on a test deployment' => [function () {
        [$plain] = IntegrationsFixtures::apiKey();

        return str_replace('bafo_test_', 'bafo_live_', $plain);
    }],
    'forged access token' => [fn () => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxIn0.c2ln'],
]);

it('answers api_access_disabled when the organization has no API access', function () {
    [$plain, $client] = IntegrationsFixtures::apiKey();
    $client->organization->forceFill(['api_enabled' => false])->save();

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))
        ->assertForbidden()
        ->assertJsonPath('code', 'api_access_disabled');
});

it('refuses a revoked access token', function () {
    [$client, $secret] = IntegrationsFixtures::oauthClient();
    $token = test()->postJson('/api/public/v1/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $secret])
        ->json('access_token');

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($token))->assertOk();

    Token::query()->update(['revoked' => true]);

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($token))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_token');
});

it('enforces the endpoint scope with insufficient_scope', function () {
    [$plain] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead]);

    test()->postJson('/api/public/v1/vendors', ['name' => 'X', 'email' => 'x@example.sa'], [
        ...IntegrationsFixtures::bearer($plain),
        'Idempotency-Key' => 'scope-test-0001',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope')
        ->assertJsonPath('details.required_scope', 'vendors:write');

    expect(Vendor::query()->count())->toBe(0);
});

it('acts as the API client for audit and idempotency', function () {
    [$plain, $client] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsWrite]);
    $headers = [...IntegrationsFixtures::bearer($plain), 'Idempotency-Key' => 'erp-intent-0001'];
    $body = ['name' => 'شركة الريادة', 'email' => 'sales@riyada.sa'];

    $first = test()->postJson('/api/public/v1/vendors', $body, $headers)->assertCreated();
    test()->postJson('/api/public/v1/vendors', $body, $headers)
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $first->json('data.id'));

    $entry = AuditLog::query()->where('action', 'vendor.created')->sole();

    expect($entry->actor_type)->toBe(ActorType::ApiClient)
        ->and($entry->actor_id)->toBe($client->id)
        ->and($entry->channel)->toBe('api')
        ->and(Vendor::query()->count())->toBe(1);
});

it('stamps last use at most once a minute', function () {
    [$plain, $client, $key] = IntegrationsFixtures::apiKey();
    Cache::flush();

    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertOk();

    $client->refresh();
    $key->refresh();
    $firstUse = $key->last_used_at;

    expect($firstUse)->not->toBeNull()
        ->and($client->last_used_at)->not->toBeNull()
        ->and($key->last_used_ip)->toBe('127.0.0.1');

    $this->travel(30)->seconds();
    test()->getJson('/api/public/v1/ping', IntegrationsFixtures::bearer($plain))->assertOk();

    expect(ApiKey::query()->find($key->id)?->last_used_at?->equalTo($firstUse))->toBeTrue();
});

it('limits a client to 300 writes a minute and reads separately', function () {
    [$plain] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead, ApiScope::VendorsWrite]);
    $headers = IntegrationsFixtures::bearer($plain);

    for ($i = 0; $i < 300; $i++) {
        test()->patchJson('/api/public/v1/vendors/'.strtolower((string) Str::ulid()), [], $headers)->assertNotFound();
    }

    test()->patchJson('/api/public/v1/vendors/'.strtolower((string) Str::ulid()), [], $headers)
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');

    test()->getJson('/api/public/v1/ping', $headers)->assertOk();
});

it('pushes client auth, the public-api limiter and bindings onto public_v1 in order', function () {
    $group = app(Router::class)->getMiddlewareGroups()['public_v1'];

    expect(array_slice($group, -3))->toBe([
        AuthenticatePublicApiClient::class,
        'throttle:public-api',
        SubstituteBindings::class,
    ]);

    $route = app(Router::class)->getRoutes()->getByName('public.v1.vendors.index');
    $middleware = app(Router::class)->gatherRouteMiddleware($route);

    expect(array_search(AuthenticatePublicApiClient::class, $middleware, true))
        ->toBeLessThan(array_search('Illuminate\Routing\Middleware\ThrottleRequests:public-api', $middleware, true));
});

it('scopes public lookups to the client organization', function () {
    [$plain] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead]);
    $foreign = Vendor::factory()->create();

    test()->getJson('/api/public/v1/vendors/'.$foreign->public_id, IntegrationsFixtures::bearer($plain))
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    expect(ApiClient::query()->count())->toBe(1);
});
