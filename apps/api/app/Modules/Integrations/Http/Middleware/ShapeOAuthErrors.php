<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token endpoint only (ARCHITECTURE §14.2, API.md §3.1): every error of `POST /oauth/token`,
 * including the 429 of `throttle:oauth-token`, carries both shapes:
 *
 *     {"error": "<code>", "error_description": "…", "message": "…", "code": "<code>", "errors": {}}
 *
 * It runs outside the throttle and the controller, so it sees their rendered error envelope.
 */
final class ShapeOAuthErrors
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse || $response->getStatusCode() < 400) {
            return $response;
        }

        $body = $response->getData(true);

        if (! is_array($body) || ! isset($body['code'], $body['message'])) {
            return $response;
        }

        $response->setData([
            'error' => $body['code'],
            'error_description' => $body['message'],
            ...$body,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
