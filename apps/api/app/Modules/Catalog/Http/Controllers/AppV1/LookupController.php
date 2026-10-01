<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\AppV1;

use App\Modules\Catalog\Http\Requests\ShowLookupRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Http\Resources\CloseReasonResource;
use App\Modules\Catalog\Http\Resources\PresetResource;
use App\Modules\Catalog\Http\Resources\RegionResource;
use App\Modules\Catalog\Queries\Lookups;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lookups for the first-party apps (API.md §1.2, guest): active rows only, ordered by
 * `sort_order`, names in the request locale.
 *
 *   GET /lookups          every list, with an ETag (If-None-Match → 304)
 *   GET /lookups/{type}   one list; `close-reasons` accepts ?kind=
 */
final class LookupController extends ApiController
{
    public function __construct(private readonly Lookups $lookups) {}

    public function index(Request $request): Response
    {
        return $this->withEtag($request, [
            'regions' => RegionResource::collection($this->lookups->regions())->resolve($request),
            'categories' => CategoryResource::collection($this->lookups->categories())->resolve($request),
            'close_reasons' => CloseReasonResource::collection($this->lookups->closeReasons())->resolve($request),
            'presets' => PresetResource::collection($this->lookups->presets())->resolve($request),
        ]);
    }

    public function show(ShowLookupRequest $request, string $type): Response
    {
        $items = match ($type) {
            Lookups::REGIONS => RegionResource::collection($this->lookups->regions()),
            Lookups::CATEGORIES => CategoryResource::collection($this->lookups->categories()),
            Lookups::CLOSE_REASONS => CloseReasonResource::collection($this->lookups->closeReasons($request->kind())),
            default => PresetResource::collection($this->lookups->presets()),
        };

        // CONTRACT-GAP: API.md §1.2 names the ETag on GET /lookups only; the single lists send
        // one too (same rule), which costs nothing and lets clients cache them the same way.
        return $this->withEtag($request, $items->resolve($request));
    }

    /**
     * The ETag covers the data and the locale (never meta.server_time, which changes every
     * request). A matching If-None-Match answers 304 without a body.
     *
     * @param  array<array-key, mixed>  $data
     */
    private function withEtag(Request $request, array $data): Response
    {
        $etag = '"'.hash('sha256', App::getLocale().'|'.json_encode($data, JSON_UNESCAPED_UNICODE)).'"';
        $headers = ['ETag' => $etag, 'Vary' => 'Accept-Language'];

        foreach ($request->getETags() as $candidate) {
            if ($candidate === '*' || $candidate === $etag || $candidate === 'W/'.$etag) {
                return response('', Response::HTTP_NOT_MODIFIED, $headers);
            }
        }

        return $this->ok($data)->withHeaders($headers);
    }
}
