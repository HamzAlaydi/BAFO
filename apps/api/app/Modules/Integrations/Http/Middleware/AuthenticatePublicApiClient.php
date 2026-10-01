<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Middleware;

use App\Modules\Integrations\Data\ApiClientContext;
use App\Modules\Integrations\Services\PublicApiAuthenticator;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `api.client`, pushed onto the `public_v1` group (ARCHITECTURE §2.2 item 7, §14.2).
 *
 * Authenticates the bearer (API key or OAuth access token), sets CurrentActor to
 * Actor::forApiClient(), and sets the request attributes `api_client`, `api_scopes` and
 * `api_client_context`. Usage stamps (`last_used_at`, `last_used_ip`) are written at most once
 * per 60 s per key or client. Every authenticated request leaves one line in the `api_access`
 * log channel (no credentials).
 */
final readonly class AuthenticatePublicApiClient
{
    private const int USAGE_STAMP_SECONDS = 60;

    public function __construct(private PublicApiAuthenticator $authenticator) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->authenticator->authenticate($request);

        $request->attributes->set(ApiClientContext::ATTRIBUTE, $context);
        $request->attributes->set('api_client', $context->client);
        $request->attributes->set('api_scopes', $context->scopes);

        CurrentActor::set(Actor::forApiClient($context->client, $request));

        $this->stampUsage($context, $request->ip());

        $started = hrtime(true);
        $response = $next($request);

        Log::channel('api_access')->info('public_api.request', [
            'api_client_id' => $context->client->public_id,
            'organization_id' => $context->client->organization_id,
            'auth' => $context->method->value,
            'key_prefix' => $context->key?->prefix,
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $response->getStatusCode(),
            'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'request_id' => $request->attributes->get('request_id'),
        ]);

        return $response;
    }

    private function stampUsage(ApiClientContext $context, ?string $ip): void
    {
        $now = Date::now();
        $client = $context->client;

        if (Cache::add('integrations:used:client:'.$client->id, true, self::USAGE_STAMP_SECONDS)) {
            $client->newQuery()->whereKey($client->id)->update(['last_used_at' => $now, 'last_used_ip' => $ip]);
        }

        $key = $context->key;

        if ($key !== null && Cache::add('integrations:used:key:'.$key->id, true, self::USAGE_STAMP_SECONDS)) {
            $key->newQuery()->whereKey($key->id)->update(['last_used_at' => $now, 'last_used_ip' => $ip]);
        }
    }
}
