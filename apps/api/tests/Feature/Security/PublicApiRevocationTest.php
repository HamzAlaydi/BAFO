<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Integrations\IntegrationsFixtures;
use Tests\Support\Integrations\PassportKeys;

/*
 * Security review (docs/build/SECURITY_REVIEW.md): the public API refuses a credential the moment
 * its client, key or organization is switched off (no caching of the decision), and every public
 * route declares a scope.
 */

beforeEach(function () {
    PassportKeys::load();
});

/**
 * @return array{0: ApiClient, 1: string} the client and a fresh access token
 */
function securityOauthToken(): array
{
    [$client, $secret] = IntegrationsFixtures::oauthClient(scopes: [ApiScope::VendorsRead]);

    $token = test()->postJson('/api/public/v1/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->public_id, 'client_secret' => $secret,
    ])->assertOk()->json('access_token');

    return [$client, (string) $token];
}

it('refuses an access token as soon as its client is suspended, revoked or its organization suspended', function (Closure $switchOff, int $status) {
    [$client, $token] = securityOauthToken();

    $this->getJson('/api/public/v1/vendors', IntegrationsFixtures::bearer($token))->assertOk();

    $switchOff($client);

    $this->getJson('/api/public/v1/vendors', IntegrationsFixtures::bearer($token))->assertStatus($status);
})->with([
    'client suspended' => [fn (ApiClient $client) => $client->forceFill(['status' => 'suspended'])->save(), 401],
    'client revoked' => [fn (ApiClient $client) => $client->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save(), 401],
    'organization suspended' => [fn (ApiClient $client) => $client->organization->forceFill(['status' => OrganizationStatus::Suspended])->save(), 401],
    'API access switched off' => [fn (ApiClient $client) => $client->organization->forceFill(['api_enabled' => false])->save(), 403],
]);

it('refuses an API key as soon as it is revoked through the dashboard', function () {
    [$organization, $owner] = IntegrationsFixtures::signIn();
    [$plain, $client, $key] = IntegrationsFixtures::apiKey(scopes: [ApiScope::VendorsRead], organization: $organization);

    $this->getJson('/api/public/v1/vendors', IntegrationsFixtures::bearer($plain))->assertOk();

    Auth::forgetGuards();
    Sanctum::actingAs($owner);
    $this->deleteJson('/api/app/v1/integrations/api-clients/'.$client->public_id.'/keys/'.$key->public_id)->assertNoContent();

    $this->getJson('/api/public/v1/vendors', IntegrationsFixtures::bearer($plain))->assertUnauthorized();
});

it('declares a scope on every public route that reaches organization data', function () {
    $unscoped = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(static fn (Route $route): bool => str_starts_with((string) $route->getName(), 'public.v1.'))
        ->reject(static fn (Route $route): bool => in_array($route->getName(), [
            'public.v1.oauth.token', 'public.v1.openapi', 'public.v1.openapi.json', 'public.v1.ping', 'public.v1.client',
        ], true))
        ->reject(static fn (Route $route): bool => collect($route->gatherMiddleware())->contains(
            static fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, 'api.scope:'),
        ))
        ->map(static fn (Route $route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($unscoped)->toBe([]);
});
