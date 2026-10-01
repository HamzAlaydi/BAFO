<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Data\ApiClientContext;
use App\Modules\Integrations\Enums\ApiAuthMethod;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Support\Exceptions\ApiException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Authenticates a public API bearer (ARCHITECTURE §14.2): an API key
 * (`bafo_{env}_{prefix8}_{secret32}`) or a Passport client-credentials access token.
 *
 * Failures throw 401 `invalid_token` (with `WWW-Authenticate: Bearer error="invalid_token"`).
 *
 * CONTRACT-GAP: §14.2 folds `api_enabled` into the 401; API.md §0.4 lists 403
 * `api_access_disabled` for public v1. A valid credential of an organization whose flag is off
 * gets the 403, so the ERP team learns that access was switched off rather than mistyped.
 */
final readonly class PublicApiAuthenticator
{
    public const string KEY_PATTERN = '/^bafo_(live|test)_([a-z0-9]{8})_[A-Za-z0-9]{32}$/';

    public function __construct(private Container $container) {}

    public function authenticate(Request $request): ApiClientContext
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            throw self::invalidToken();
        }

        if (preg_match(self::KEY_PATTERN, $token, $matches) === 1) {
            return $this->authenticateKey($token, $matches[1], $matches[2]);
        }

        return $this->authenticateAccessToken($request);
    }

    /**
     * Throws unless the client may use the API: active, its organization active and API-enabled.
     */
    public static function assertClientUsable(?ApiClient $client): ApiClient
    {
        $organization = $client?->organization;

        if ($client === null || ! $client->isActive() || $organization === null || ! $organization->isActive()) {
            throw self::invalidToken();
        }

        if (! $organization->api_enabled) {
            throw new ApiException('api_access_disabled', 'integrations.errors.api_access_disabled', 403);
        }

        return $client;
    }

    public static function invalidToken(): ApiException
    {
        return new ApiException(
            errorCode: 'invalid_token',
            messageKey: 'integrations.errors.invalid_token',
            status: 401,
            headers: ['WWW-Authenticate' => 'Bearer error="invalid_token"'],
        );
    }

    private function authenticateKey(string $plainKey, string $environment, string $prefix): ApiClientContext
    {
        // CONTRACT-GAP: §14.2 does not say what happens to a key of the other environment. A
        // `bafo_live_…` key is refused by a test deployment and vice versa, so test keys never
        // reach production data.
        if ($environment !== config('bafo.integrations.key_environment')) {
            throw self::invalidToken();
        }

        $key = ApiKey::query()->with('apiClient.organization')->where('prefix', $prefix)->first();

        if ($key === null || ! hash_equals($key->key_hash, hash('sha256', $plainKey)) || ! $key->isUsableAt(Date::now())) {
            throw self::invalidToken();
        }

        $client = self::assertClientUsable($key->apiClient);

        return new ApiClientContext($client, $client->scopes, ApiAuthMethod::ApiKey, $key);
    }

    private function authenticateAccessToken(Request $request): ApiClientContext
    {
        try {
            $validated = $this->container->make(ResourceServer::class)
                ->validateAuthenticatedRequest((new PsrHttpFactory)->createRequest($request));
        } catch (OAuthServerException) {
            throw self::invalidToken();
        }

        $oauthClientId = $validated->getAttribute('oauth_client_id');

        if (! is_string($oauthClientId) || $oauthClientId === '') {
            throw self::invalidToken();
        }

        $client = self::assertClientUsable(
            ApiClient::query()->with('organization')->where('oauth_client_id', $oauthClientId)->first(),
        );

        $tokenScopes = $validated->getAttribute('oauth_scopes');
        $tokenScopes = is_array($tokenScopes) ? array_filter($tokenScopes, is_string(...)) : [];

        // Narrowing the client's scopes applies to tokens already issued (§14.2: token ∩ client).
        $scopes = array_values(array_intersect($tokenScopes, $client->scopes));

        return new ApiClientContext($client, $scopes, ApiAuthMethod::OAuth);
    }
}
