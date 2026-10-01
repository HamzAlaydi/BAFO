<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\PublicV1;

use App\Modules\Integrations\Services\OpenApi\OpenApiDocument;
use Illuminate\Http\Response;

/**
 * The public v1 OpenAPI 3.1 document (ARCHITECTURE §14.9), without authentication:
 * `GET /openapi.yaml` (`application/yaml`, API.md §3.1) and `GET /openapi.json`.
 */
final class OpenApiController
{
    public function yaml(OpenApiDocument $document): Response
    {
        return new Response($document->yaml(), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function json(OpenApiDocument $document): Response
    {
        return new Response($document->json(), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
