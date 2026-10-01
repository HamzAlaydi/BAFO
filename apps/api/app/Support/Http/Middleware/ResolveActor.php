<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets CurrentActor for app v1 (ARCHITECTURE §4.1): Actor::forUser() for the Sanctum user,
 * with the channel from X-Platform, or a guest actor on guest routes (those that call
 * ->withoutMiddleware('auth:sanctum')).
 */
final class ResolveActor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        CurrentActor::set($user !== null ? Actor::forUser($user, $request) : Actor::guest($request));

        return $next($request);
    }
}
