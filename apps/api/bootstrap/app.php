<?php

declare(strict_types=1);

use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ApiExceptionRenderer;
use App\Support\Http\Middleware\AssignRequestId;
use App\Support\Http\Middleware\BlockDuringMaintenance;
use App\Support\Http\Middleware\EnsureSupportedAppVersion;
use App\Support\Http\Middleware\ForceJsonResponse;
use App\Support\Http\Middleware\IdempotentRequest;
use App\Support\Http\Middleware\ResolveActor;
use App\Support\Http\Middleware\SecurityHeaders;
use App\Support\Http\Middleware\SetLocaleFromHeader;
use App\Support\Routing\ApiRoutes;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static fn () => ApiRoutes::register(__DIR__.'/../routes'),
    )
    // Channel auth for Reverb: POST /broadcasting/auth with a Sanctum bearer token.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => [ForceJsonResponse::class, AssignRequestId::class, SetLocaleFromHeader::class, 'auth:sanctum'],
    ])
    ->withMiddleware(static function (Middleware $middleware): void {
        // Only the module-agnostic part of each group lives here (ARCHITECTURE §2.2 item 7).
        // Modules append their own middleware with Router::pushMiddlewareToGroup() in boot():
        // Identity appends EnsureAccountActive to app_v1; Integrations appends `api.client`,
        // `throttle:public-api` and SubstituteBindings to public_v1.

        // First-party clients (web + mobile). Sanctum auth is on by default; guest endpoints
        // use Route::withoutMiddleware('auth:sanctum') and get a guest actor. SubstituteBindings
        // stays last: route-model binding runs after authentication.
        $middleware->group('app_v1', [
            ForceJsonResponse::class,
            AssignRequestId::class,
            SetLocaleFromHeader::class,
            EnsureSupportedAppVersion::class,
            BlockDuringMaintenance::class,
            'auth:sanctum',
            ResolveActor::class,
            'throttle:app',
            SubstituteBindings::class,
        ]);

        // Public ERP API (R1).
        $middleware->group('public_v1', [
            ForceJsonResponse::class,
            AssignRequestId::class,
            SetLocaleFromHeader::class,
        ]);

        // Response hardening on every route (SECURITY_REVIEW S-05): nosniff, referrer and frame
        // policies, HSTS over HTTPS, and a deny-all CSP on API responses.
        $middleware->append(SecurityHeaders::class);

        // Idempotency-Key handling (§4.8): `idempotent` (required) or `idempotent:optional`.
        $middleware->alias([
            'idempotent' => IdempotentRequest::class,
        ]);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => ApiExceptionRenderer::shouldRender($request) || $request->expectsJson(),
        );

        $exceptions->render(
            static fn (Throwable $e, Request $request) => ApiExceptionRenderer::shouldRender($request)
                ? ApiExceptionRenderer::render($e, $request)
                : null,
        );

        $exceptions->dontReportWhen(
            static fn (Throwable $e): bool => $e instanceof ApiException && ! $e->shouldReport(),
        );
    })->create();
