<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * X-Request-Id (ARCHITECTURE §4.8): accepts the client's id (8–64 chars `[A-Za-z0-9-]`) or
 * generates a ULID, echoes it on the response and adds it to the log context. Laravel
 * Context carries it into queued jobs; Actor and AuditLogger record it.
 */
final class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public const string ATTRIBUTE = 'request_id';

    private const string PATTERN = '/^[A-Za-z0-9-]{8,64}$/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = self::resolve($request);

        $request->attributes->set(self::ATTRIBUTE, $id);
        $request->headers->set(self::HEADER, $id);
        Context::add(self::ATTRIBUTE, $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    public static function resolve(Request $request): string
    {
        $given = $request->attributes->get(self::ATTRIBUTE) ?? $request->headers->get(self::HEADER);

        return is_string($given) && preg_match(self::PATTERN, $given) === 1
            ? $given
            : strtolower((string) Str::ulid());
    }
}
