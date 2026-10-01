<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use stdClass;

/**
 * Builds the BAFO success envelope: { "data": ..., "meta": { ... } }.
 *
 * Resources are resolved without their own "data" wrapper, and paginators add
 * meta.pagination (page-based or cursor-based). Responses of app v1 routes (middleware
 * group "app_v1") always carry meta.server_time; public v1 responses do not (API.md §0.4, §3.0).
 */
final class ApiResponse
{
    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    public static function ok(mixed $data, array $meta = [], int $status = 200, array $headers = []): JsonResponse
    {
        $meta = self::withServerTime($meta);

        return new JsonResponse(
            ['data' => self::resolve($data), 'meta' => $meta === [] ? new stdClass : $meta],
            $status,
            $headers,
            self::JSON_FLAGS,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function created(mixed $data, array $meta = []): JsonResponse
    {
        return self::ok($data, $meta, 201);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * @param  Paginator<array-key, mixed>|CursorPaginator<array-key, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource  resource applied to each item
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(Paginator|CursorPaginator $paginator, ?string $resource = null, array $meta = []): JsonResponse
    {
        $items = Collection::make($paginator->items());
        $data = $resource !== null ? $resource::collection($items) : $items;

        return self::ok($data, ['pagination' => self::pagination($paginator), ...$meta]);
    }

    /**
     * @param  Paginator<array-key, mixed>|CursorPaginator<array-key, mixed>  $paginator
     * @return array<string, mixed>
     */
    public static function pagination(Paginator|CursorPaginator $paginator): array
    {
        if ($paginator instanceof CursorPaginator) {
            return [
                'type' => 'cursor',
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ];
        }

        $pagination = [
            'type' => 'page',
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ];

        if ($paginator instanceof LengthAwarePaginator) {
            $pagination['total'] = $paginator->total();
            $pagination['last_page'] = $paginator->lastPage();
        }

        return $pagination;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function withServerTime(array $meta): array
    {
        $route = request()->route();

        if (! $route instanceof Route || array_key_exists('server_time', $meta)
            || ! in_array('app_v1', $route->gatherMiddleware(), true)) {
            return $meta;
        }

        return [...$meta, 'server_time' => Iso::format(Date::now())];
    }

    private static function resolve(mixed $data): mixed
    {
        if ($data instanceof ResourceCollection || $data instanceof JsonResource) {
            return $data->resolve(request());
        }

        return $data;
    }
}
