<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\PublicV1;

use App\Modules\Integrations\Data\ApiClientContext;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Support\Http\Controllers\ApiController;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * `GET /ping` and `GET /client` (API.md §3.1): any valid credential.
 */
final class MetaController extends ApiController
{
    public function ping(): JsonResponse
    {
        return $this->ok(['pong' => true, 'server_time' => Iso::format(Date::now())]);
    }

    public function client(Request $request): JsonResponse
    {
        $context = ApiClientContext::from($request);
        $context->client->loadMissing('organization');

        return $this->ok([
            'client_id' => $context->client->public_id,
            'name' => $context->client->name,
            'organization_id' => $context->client->organization?->public_id,
            'scopes' => $context->scopes,
            'auth' => $context->method->value,
            'environment' => ApiKeyGenerator::environment(),
            'rate_limits' => [
                'per_minute_read' => (int) config('bafo.integrations.rate_limits.per_minute_read'),
                'per_minute_write' => (int) config('bafo.integrations.rate_limits.per_minute_write'),
            ],
        ]);
    }
}
