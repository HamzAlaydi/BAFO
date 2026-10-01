<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Database\Factories\ApiKeyFactory;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Illuminate\Routing\Router;

/**
 * Calls to the public API as an API client of an organization.
 *
 * With Integrations' `api.client` middleware registered, this creates a real API key and returns
 * its bearer header. Until then (parallel build), it registers StandInApiScope as `api.scope` and
 * puts the client on the current actor, which is what `api.client` does (§14.2).
 */
final class PublicApi
{
    /**
     * @param  list<ApiScope>  $scopes
     * @return array<string, string>
     */
    public static function headers(Organization $organization, array $scopes): array
    {
        $organization->forceFill(['api_enabled' => true])->save();

        $client = ApiClient::factory()->scopes($scopes)->create(['organization_id' => $organization->id]);
        $router = app(Router::class);

        if (array_key_exists('api.client', $router->getMiddleware())) {
            $plainKey = ApiKeyFactory::makePlainKey();
            ApiKey::factory()->withPlainKey($plainKey)->create(['api_client_id' => $client->id]);

            return ['Authorization' => 'Bearer '.$plainKey];
        }

        $router->aliasMiddleware('api.scope', StandInApiScope::class);
        CurrentActor::set(Actor::forApiClient($client));

        return [];
    }
}
