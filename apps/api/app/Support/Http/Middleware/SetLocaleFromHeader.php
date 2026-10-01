<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the request locale from Accept-Language (ar|en, default ar) and echoes it
 * back in Content-Language. Unknown or missing values fall back to the default.
 */
final class SetLocaleFromHeader
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    public static function resolve(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['ar', 'en']);
        $default = (string) config('app.default_locale', 'ar');

        // Default first: Symfony returns the first supported locale when nothing matches.
        $ordered = array_values(array_unique([$default, ...$supported]));

        if (trim((string) $request->headers->get('Accept-Language', '')) === '') {
            return $default;
        }

        return $request->getPreferredLanguage($ordered) ?? $default;
    }
}
