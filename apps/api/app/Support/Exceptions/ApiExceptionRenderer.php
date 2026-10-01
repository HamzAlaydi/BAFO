<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Http\Middleware\AssignRequestId;
use App\Support\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception raised under /api/* as the BAFO error envelope:
 *
 *     { "message": "...", "code": "snake_case_code", "errors": { "field": ["..."] } }
 *
 * plus "details" when an ApiException carries details (and `retry_after_seconds` on 429).
 * Messages are localised with the request locale (see SetLocaleFromHeader).
 * With APP_DEBUG on, a "debug" key carries the exception class, message and origin.
 */
final class ApiExceptionRenderer
{
    /**
     * HTTP status => generic code used when an exception carries no code of its own.
     *
     * @var array<int, string>
     */
    private const array STATUS_CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        406 => 'not_acceptable',
        409 => 'conflict',
        410 => 'gone',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        419 => 'session_expired',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        500 => 'server_error',
        503 => 'service_unavailable',
    ];

    /**
     * Only the API surfaces (and Reverb channel auth); Filament and Livewire keep the
     * framework's own rendering.
     */
    public static function shouldRender(Request $request): bool
    {
        return $request->is('api', 'api/*', 'broadcasting/*');
    }

    public static function render(Throwable $e, Request $request): JsonResponse
    {
        // Errors raised before the route middleware ran (unknown route, 405) still honour Accept-Language.
        $locale = SetLocaleFromHeader::resolve($request);
        App::setLocale($locale);

        [$status, $code, $message, $errors, $headers] = self::describe($e);
        $headers['Content-Language'] = $locale;

        $requestId = $request->attributes->get(AssignRequestId::ATTRIBUTE);

        if (is_string($requestId)) {
            $headers[AssignRequestId::HEADER] = $requestId;
        }

        $body = [
            'message' => $message,
            'code' => $code,
            'errors' => $errors === [] ? new stdClass : $errors,
        ];

        $details = self::details($e, $headers);

        if ($details !== []) {
            $body['details'] = $details;
        }

        if (config('app.debug') && $status >= 500) {
            $body['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return new JsonResponse($body, $status, $headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>, 4: array<string, string>}
     */
    private static function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof ApiException => [
                $e->status, $e->errorCode, $e->translatedMessage(), $e->errors, $e->headers,
            ],
            $e instanceof ValidationException => [
                $e->status, 'validation_failed', self::translate('validation_failed'), $e->errors(), [],
            ],
            $e instanceof AuthenticationException => [
                401, 'unauthenticated', self::translate('unauthenticated'), [], [],
            ],
            $e instanceof AuthorizationException => self::describeAuthorization($e),
            $e instanceof ModelNotFoundException, $e instanceof RecordsNotFoundException,
            $e instanceof RecordNotFoundException, $e instanceof BackedEnumCaseNotFoundException => [
                404, 'not_found', self::translate('not_found'), [], [],
            ],
            $e instanceof RequestExceptionInterface => [
                400, 'bad_request', self::translate('bad_request'), [], [],
            ],
            $e instanceof TokenMismatchException => [
                419, 'session_expired', self::translate('session_expired'), [], [],
            ],
            $e instanceof ThrottleRequestsException => [
                429, 'too_many_requests', self::translate('too_many_requests'), [], self::stringHeaders($e->getHeaders()),
            ],
            $e instanceof HttpExceptionInterface => self::describeHttp($e),
            default => [500, 'server_error', self::translate('server_error'), [], []],
        };
    }

    /**
     * Machine-readable extras: an ApiException's own details; `retry_after_seconds` on 429.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private static function details(Throwable $e, array $headers): array
    {
        if ($e instanceof ApiException) {
            return $e->details;
        }

        if ($e instanceof ThrottleRequestsException && is_numeric($headers['Retry-After'] ?? null)) {
            return ['retry_after_seconds' => (int) $headers['Retry-After']];
        }

        return [];
    }

    /**
     * Policies may deny with a custom (already translated) message or as a 404.
     *
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>, 4: array<string, string>}
     */
    private static function describeAuthorization(AuthorizationException $e): array
    {
        $status = $e->hasStatus() ? (int) $e->status() : 403;
        $code = self::STATUS_CODES[$status] ?? 'forbidden';
        $custom = $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.';

        return [$status, $code, $custom ? $e->getMessage() : self::translate($code), [], []];
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>, 4: array<string, string>}
     */
    private static function describeHttp(HttpExceptionInterface $e): array
    {
        $status = $e->getStatusCode();
        $code = self::STATUS_CODES[$status] ?? ($status >= 500 ? 'server_error' : 'http_error');

        // `php artisan down` raises a 503 HttpException.
        if ($status === 503 && app()->isDownForMaintenance()) {
            $code = 'maintenance';
        }

        return [$status, $code, self::translate($code), [], self::stringHeaders($e->getHeaders())];
    }

    private static function translate(string $code): string
    {
        $message = __('errors.'.$code);

        return is_string($message) ? $message : $code;
    }

    /**
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    private static function stringHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $out[(string) $name] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        }

        return $out;
    }
}
