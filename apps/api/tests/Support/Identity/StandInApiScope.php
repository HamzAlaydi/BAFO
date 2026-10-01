<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Support\Auth\CurrentActor;
use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test stand-in for Integrations' `api.scope` middleware (ARCHITECTURE §14.2), used only while
 * Integrations has not registered the real alias: it enforces the scope of the API client that
 * PublicApi put on the current actor.
 */
final class StandInApiScope
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $actor = CurrentActor::get();
        $client = $actor->apiClientId !== null ? ApiClient::query()->find($actor->apiClientId) : null;

        if ($client === null) {
            throw new ApiException('invalid_token', status: 401);
        }

        if (! $client->hasScope(ApiScope::from($scope))) {
            throw new ApiException('insufficient_scope', status: 403, details: ['required_scope' => $scope]);
        }

        return $next($request);
    }
}
