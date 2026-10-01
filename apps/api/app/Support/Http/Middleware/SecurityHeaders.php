<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global response hardening (SECURITY_REVIEW S-05). Every response gets:
 *
 *   X-Content-Type-Options: nosniff        no MIME sniffing of JSON or downloads
 *   Referrer-Policy: same-origin           no URL (and nothing in it) leaves the origin
 *   X-Frame-Options                        DENY for the API, SAMEORIGIN for the admin panel
 *   Strict-Transport-Security              on HTTPS requests only
 *
 * API responses (`api/*`, `broadcasting/*`) also get `Content-Security-Policy: default-src 'none';
 * frame-ancestors 'none'`, so a JSON body or a streamed file opened in a browser can never run
 * script. The web pages (Filament, the fake checkout page, /docs/api) keep their own policy.
 * A header a controller already set is left as it is.
 */
final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $api = $request->is('api/*', 'broadcasting/*');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => $api ? 'DENY' : 'SAMEORIGIN',
        ];

        if ($api) {
            $headers['Content-Security-Policy'] = "default-src 'none'; frame-ancestors 'none'";
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
