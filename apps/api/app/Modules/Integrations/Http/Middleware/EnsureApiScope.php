<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Middleware;

use App\Modules\Integrations\Data\ApiClientContext;
use App\Modules\Integrations\Enums\ApiScope;
use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `api.scope` (ARCHITECTURE §14.2): `->middleware('api.scope:competitions:read')`.
 * A request whose effective scopes lack the scope gets 403 `insufficient_scope` with
 * `details.required_scope`.
 */
final class EnsureApiScope
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $required = ApiScope::from($scope);

        if (! ApiClientContext::from($request)->allows($required)) {
            throw new ApiException(
                errorCode: 'insufficient_scope',
                messageKey: 'integrations.errors.insufficient_scope',
                status: 403,
                replace: ['scope' => $required->value],
                details: ['required_scope' => $required->value],
            );
        }

        return $next($request);
    }
}
