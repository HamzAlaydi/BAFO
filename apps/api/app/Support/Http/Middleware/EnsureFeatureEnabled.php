<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Support\Features\FeatureFlags;
use App\Support\Features\FeatureRefusals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `feature:<flag>` (RELEASE_SCOPE.md §1.5): a route of a feature that the current release
 * scope hides answers 404 `feature_disabled` with `details.feature`, as if it did not exist.
 *
 *   Route::post('{competition}/bafo-round', ...)->middleware('feature:bafo_round');
 *
 * It ranks before route-model binding and `can:` (bootstrap/app.php), so the answer is the same
 * whatever the parameters or the caller's permissions. Flags that are on in both scopes still
 * carry the middleware so a later release can flip them without touching the routes. An unknown
 * flag name is a programming error and throws.
 */
final readonly class EnsureFeatureEnabled
{
    public function __construct(private FeatureFlags $flags) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->flags->enabled($feature)) {
            throw FeatureRefusals::disabled($feature);
        }

        return $next($request);
    }
}
