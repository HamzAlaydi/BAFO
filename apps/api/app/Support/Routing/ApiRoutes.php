<?php

declare(strict_types=1);

namespace App\Support\Routing;

use Illuminate\Support\Facades\Route;

/**
 * Loads the per-module API route files:
 *
 *   routes/app_v1/<module>.php     => /api/app/v1/...     middleware group "app_v1", names "app.v1.*"
 *   routes/public_v1/<module>.php  => /api/public/v1/...  middleware group "public_v1", names "public.v1.*"
 *
 * "app_v1" authenticates with Sanctum by default. Guest endpoints opt out explicitly:
 *
 *   Route::withoutMiddleware('auth:sanctum')->group(function () { ... });
 */
final class ApiRoutes
{
    /**
     * @var array<string, array{prefix: string, middleware: string, name: string}>
     */
    public const array SURFACES = [
        'app_v1' => ['prefix' => 'api/app/v1', 'middleware' => 'app_v1', 'name' => 'app.v1.'],
        'public_v1' => ['prefix' => 'api/public/v1', 'middleware' => 'public_v1', 'name' => 'public.v1.'],
    ];

    public static function register(string $routesPath): void
    {
        foreach (self::SURFACES as $directory => $surface) {
            $files = glob($routesPath.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'*.php') ?: [];
            sort($files);

            foreach ($files as $file) {
                Route::prefix($surface['prefix'])
                    ->middleware($surface['middleware'])
                    ->name($surface['name'])
                    ->group($file);
            }
        }
    }
}
