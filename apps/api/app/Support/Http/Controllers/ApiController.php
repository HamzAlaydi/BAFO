<?php

declare(strict_types=1);

namespace App\Support\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base controller for /api/app/v1 and /api/public/v1. Always answer through these
 * helpers so every response keeps the { data, meta } envelope.
 */
abstract class ApiController
{
    use AuthorizesRequests;

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function ok(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return ApiResponse::ok($data, $meta, $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function created(mixed $data, array $meta = []): JsonResponse
    {
        return ApiResponse::created($data, $meta);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    /**
     * @param  Paginator<array-key, mixed>|CursorPaginator<array-key, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource
     * @param  array<string, mixed>  $meta
     */
    protected function paginated(Paginator|CursorPaginator $paginator, ?string $resource = null, array $meta = []): JsonResponse
    {
        return ApiResponse::paginated($paginator, $resource, $meta);
    }
}
