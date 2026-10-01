<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Middleware;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `integrations.access` (app v1, API.md §1.9 permission table):
 *
 *   ->middleware('integrations.access')       `integrations.manage` (imports and exports)
 *   ->middleware('integrations.access:api')   plus `organizations.api_enabled`, otherwise 403
 *                                             `api_access_disabled` (API clients, keys, webhooks)
 */
final class EnsureIntegrationsAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasPermission(Permission::IntegrationsManage)) {
            throw new AuthorizationException;
        }

        if ($requirement === 'api' && $user->membership?->organization?->api_enabled !== true) {
            throw new ApiException('api_access_disabled', 'integrations.errors.api_access_disabled', 403);
        }

        return $next($request);
    }
}
