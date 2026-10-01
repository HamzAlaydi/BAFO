<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\PublicV1;

use App\Modules\Catalog\Http\Requests\ShowLookupRequest;
use App\Modules\Catalog\Http\Resources\PublicLookupResource;
use App\Modules\Catalog\Queries\Lookups;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/public/v1/lookups/{type}` (scope `lookups:read`, API.md §3.2): regions, categories
 * or close reasons, with `name` as `{"ar", "en"}`. Not paginated.
 */
final class LookupController extends ApiController
{
    public function __invoke(ShowLookupRequest $request, string $type, Lookups $lookups): JsonResponse
    {
        $items = match ($type) {
            Lookups::REGIONS => $lookups->regions(),
            Lookups::CATEGORIES => $lookups->categories(),
            // CONTRACT-GAP: API.md §3.2 does not mention ?kind= for the public list; it is accepted
            // with the same meaning as on the app API.
            default => $lookups->closeReasons($request->kind()),
        };

        return $this->ok(PublicLookupResource::collection($items));
    }
}
