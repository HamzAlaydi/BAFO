<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\ApiClient;
use App\Support\Exceptions\ApiException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * `POST /api/public/v1/oauth/token` (ARCHITECTURE §14.2 steps 1–6): validates the grant, the
 * BAFO client and the scopes, then delegates to Passport's AccessTokenController with the
 * Passport client id. Returns the RFC 6749 body.
 */
final readonly class AccessTokenIssuer
{
    public const string GRANT_TYPE = 'client_credentials';

    public function __construct(private Container $container) {}

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, scope: string}
     */
    public function issue(Request $request): array
    {
        // 1. Input: HTTP Basic, or client_id / client_secret in the (form or JSON) body.
        $usesBasic = $request->getUser() !== null;
        $clientId = $usesBasic ? (string) $request->getUser() : self::string($request->input('client_id'));
        $clientSecret = $usesBasic ? (string) $request->getPassword() : self::string($request->input('client_secret'));

        // 2. Grant. CONTRACT-GAP: a missing grant_type is answered like an unsupported one (API.md
        // §3.1 lists no `invalid_request`).
        if (self::string($request->input('grant_type')) !== self::GRANT_TYPE) {
            throw new ApiException('unsupported_grant_type', 'integrations.errors.unsupported_grant_type', 400);
        }

        // 3. Client: an active BAFO client of an active, API-enabled organization.
        $client = $this->findClient($clientId, $usesBasic);

        // 4. Scope ⊆ api_clients.scopes; all of them when absent.
        $scopes = $this->scopes($request->input('scope'), $client);

        // 5. Delegate to Passport with its own client id.
        $psrRequest = (new PsrHttpFactory)->createRequest($request)
            ->withoutHeader('Authorization')
            ->withParsedBody([
                'grant_type' => self::GRANT_TYPE,
                'client_id' => (string) $client->oauth_client_id,
                'client_secret' => $clientSecret,
                'scope' => implode(' ', $scopes),
            ]);

        try {
            $response = $this->container->make(AccessTokenController::class)
                ->issueToken($psrRequest, $this->container->make(ResponseInterface::class));
        } catch (OAuthServerException $e) {
            $previous = $e->getPrevious();

            throw $this->translate(
                $previous instanceof LeagueOAuthServerException ? $previous->getErrorType() : 'invalid_client',
                $usesBasic,
            );
        }

        /** @var array{access_token?: mixed, token_type?: mixed, expires_in?: mixed} $body */
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // 6. The RFC 6749 shape, with the granted scope echoed.
        return [
            'access_token' => (string) ($body['access_token'] ?? ''),
            'token_type' => 'Bearer',
            'expires_in' => (int) ($body['expires_in'] ?? 0),
            'scope' => implode(' ', $scopes),
        ];
    }

    private function findClient(string $clientId, bool $usesBasic): ApiClient
    {
        $client = Str::isUlid($clientId)
            ? ApiClient::query()->with('organization')->wherePublicId($clientId)->first()
            : null;

        $organization = $client?->organization;

        if ($client === null || ! $client->isActive() || $client->oauth_client_id === null
            || $organization === null || ! $organization->isActive() || ! $organization->api_enabled) {
            throw $this->translate('invalid_client', $usesBasic);
        }

        return $client;
    }

    /**
     * @return list<string>
     */
    private function scopes(mixed $requested, ApiClient $client): array
    {
        $requested = self::string($requested);

        if (trim($requested) === '') {
            return $client->scopes;
        }

        $scopes = array_values(array_unique(preg_split('/\s+/', trim($requested)) ?: []));

        if (array_diff($scopes, $client->scopes) !== []) {
            throw new ApiException('invalid_scope', 'integrations.errors.invalid_scope', 400);
        }

        return $scopes;
    }

    private function translate(string $leagueError, bool $usesBasic): ApiException
    {
        return match ($leagueError) {
            'invalid_scope' => new ApiException('invalid_scope', 'integrations.errors.invalid_scope', 400),
            'unsupported_grant_type' => new ApiException('unsupported_grant_type', 'integrations.errors.unsupported_grant_type', 400),
            default => new ApiException(
                errorCode: 'invalid_client',
                messageKey: 'integrations.errors.invalid_client',
                status: 401,
                // RFC 6749 §5.2: a client that authenticated with HTTP Basic gets the challenge back.
                headers: $usesBasic ? ['WWW-Authenticate' => 'Basic realm="BAFO API"'] : [],
            ),
        };
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
