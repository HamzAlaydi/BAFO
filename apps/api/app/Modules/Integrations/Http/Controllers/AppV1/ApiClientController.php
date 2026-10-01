<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\AppV1;

use App\Modules\Integrations\Actions\ApiClients\CreateApiClient;
use App\Modules\Integrations\Actions\ApiClients\CreateApiKey;
use App\Modules\Integrations\Actions\ApiClients\RevokeApiClient;
use App\Modules\Integrations\Actions\ApiClients\RevokeApiKey;
use App\Modules\Integrations\Actions\ApiClients\RotateApiClientSecret;
use App\Modules\Integrations\Actions\ApiClients\UpdateApiClient;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
use App\Modules\Integrations\Http\Requests\StoreApiClientRequest;
use App\Modules\Integrations\Http\Requests\StoreApiKeyRequest;
use App\Modules\Integrations\Http\Requests\UpdateApiClientRequest;
use App\Modules\Integrations\Http\Resources\ApiClientResource;
use App\Modules\Integrations\Http\Resources\ApiKeyResource;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API clients and keys in the dashboard (API.md §1.9, ARCHITECTURE §14.1). The route group
 * applies `integrations.access:api` (`integrations.manage` and `api_enabled`); the policy scopes
 * every client to the user's organization.
 */
final class ApiClientController extends ApiController
{
    use ResolvesTenant;

    public function index(Request $request): JsonResponse
    {
        $clients = ApiClient::query()
            ->with(ApiClientResource::RELATIONS)
            ->where('organization_id', $this->organization($request)->id)
            ->orderByDesc('id')
            ->get();

        return $this->ok(ApiClientResource::collection($clients));
    }

    public function store(StoreApiClientRequest $request, CreateApiClient $create): JsonResponse
    {
        $issued = $create->handle($this->organization($request), $request->clientData(), CurrentActor::get());

        return $this->created((new ApiClientResource($issued->client))->withClientSecret($issued->clientSecret));
    }

    public function show(ApiClient $client): JsonResponse
    {
        $this->authorize('manage', $client);

        return $this->ok(new ApiClientResource($client));
    }

    public function update(UpdateApiClientRequest $request, ApiClient $client, UpdateApiClient $update): JsonResponse
    {
        $this->authorize('manage', $client);

        return $this->ok(new ApiClientResource($update->handle($client, $request->clientData(), CurrentActor::get())));
    }

    public function destroy(ApiClient $client, RevokeApiClient $revoke): JsonResponse
    {
        $this->authorize('manage', $client);

        $revoke->handle($client, CurrentActor::get());

        return $this->noContent();
    }

    public function rotateSecret(ApiClient $client, RotateApiClientSecret $rotate): JsonResponse
    {
        $this->authorize('manage', $client);

        $issued = $rotate->handle($client, CurrentActor::get());

        return $this->ok((new ApiClientResource($issued->client))->withClientSecret($issued->clientSecret));
    }

    public function storeKey(StoreApiKeyRequest $request, ApiClient $client, CreateApiKey $create): JsonResponse
    {
        $this->authorize('manage', $client);

        $issued = $create->handle($client, $request->expiresInDays(), CurrentActor::get());

        return $this->created((new ApiKeyResource($issued->key))->withPlainKey($issued->plainKey));
    }

    public function destroyKey(ApiClient $client, ApiKey $key, RevokeApiKey $revoke): JsonResponse
    {
        $this->authorize('manage', $client);

        if ($key->api_client_id !== $client->id) {
            throw new ModelNotFoundException;
        }

        $revoke->handle($key, CurrentActor::get());

        return $this->noContent();
    }
}
