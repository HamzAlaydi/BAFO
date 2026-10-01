<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\PublicV1;

use App\Modules\Integrations\Actions\Webhooks\CreateWebhookEndpoint;
use App\Modules\Integrations\Actions\Webhooks\DeleteWebhookEndpoint;
use App\Modules\Integrations\Actions\Webhooks\RedeliverWebhook;
use App\Modules\Integrations\Actions\Webhooks\RotateWebhookEndpointSecret;
use App\Modules\Integrations\Actions\Webhooks\SendTestWebhook;
use App\Modules\Integrations\Actions\Webhooks\UpdateWebhookEndpoint;
use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
use App\Modules\Integrations\Http\Controllers\AppV1\WebhookEndpointController as AppWebhookEndpointController;
use App\Modules\Integrations\Http\Requests\StoreWebhookEndpointRequest;
use App\Modules\Integrations\Http\Requests\UpdateWebhookEndpointRequest;
use App\Modules\Integrations\Http\Resources\WebhookDeliveryResource;
use App\Modules\Integrations\Http\Resources\WebhookEndpointResource;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public webhook endpoints (API.md §3.6), scope `webhooks:manage`: the same operations as the
 * dashboard, on the API client's organization.
 */
final class WebhookEndpointController extends ApiController
{
    use ResolvesTenant;

    public function eventTypes(): JsonResponse
    {
        return $this->ok(AppWebhookEndpointController::catalogue());
    }

    public function index(Request $request): JsonResponse
    {
        $endpoints = WebhookEndpoint::query()
            ->where('organization_id', $this->organization($request)->id)
            ->orderByDesc('id')
            ->get();

        return $this->ok(WebhookEndpointResource::collection($endpoints));
    }

    public function store(StoreWebhookEndpointRequest $request, CreateWebhookEndpoint $create): JsonResponse
    {
        $issued = $create->handle($this->organization($request), $request->endpointData(), CurrentActor::get());

        return $this->created((new WebhookEndpointResource($issued->endpoint))->withSecret($issued->secret))
            ->header('Location', url('/api/public/v1/webhook-endpoints/'.$issued->endpoint->public_id));
    }

    public function show(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $this->ensureOwnedByClient($request, $endpoint->organization_id);

        return $this->ok(new WebhookEndpointResource($endpoint));
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $endpoint, UpdateWebhookEndpoint $update): JsonResponse
    {
        $this->ensureOwnedByClient($request, $endpoint->organization_id);

        return $this->ok(new WebhookEndpointResource($update->handle($endpoint, $request->endpointData(), CurrentActor::get())));
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint, DeleteWebhookEndpoint $delete): JsonResponse
    {
        $this->ensureOwnedByClient($request, $endpoint->organization_id);

        $delete->handle($endpoint, CurrentActor::get());

        return $this->noContent();
    }

    public function test(Request $request, WebhookEndpoint $endpoint, SendTestWebhook $send): JsonResponse
    {
        $this->ensureOwnedByClient($request, $endpoint->organization_id);

        return $this->ok(['event_id' => $send->handle($endpoint, CurrentActor::get())->public_id], status: 202);
    }

    public function rotateSecret(Request $request, WebhookEndpoint $endpoint, RotateWebhookEndpointSecret $rotate): JsonResponse
    {
        $this->ensureOwnedByClient($request, $endpoint->organization_id);

        $issued = $rotate->handle($endpoint, CurrentActor::get());

        return $this->ok((new WebhookEndpointResource($issued->endpoint))->withSecret($issued->secret));
    }

    /**
     * Newest first, cursor-paginated.
     *
     * CONTRACT-GAP: API.md §0.5 orders public lists by `updated_at, id`; the delivery log keeps the
     * dashboard's "newest first" (§1.9), which is what a log reader expects.
     */
    public function deliveries(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $this->ensureOwnedByClient($request, $endpoint->organization_id);

        $filters = $request->validate(['status' => ['nullable', 'string', Rule::enum(DeliveryStatus::class)]]);

        $deliveries = WebhookDelivery::query()
            ->with('event')
            ->where('webhook_endpoint_id', $endpoint->id)
            ->when(isset($filters['status']), static fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request, 50, 200));

        return $this->paginated($deliveries, WebhookDeliveryResource::class);
    }

    public function redeliver(Request $request, WebhookDelivery $delivery, RedeliverWebhook $redeliver): JsonResponse
    {
        $organizationId = WebhookEndpoint::withTrashed()->whereKey($delivery->webhook_endpoint_id)->value('organization_id');
        $this->ensureOwnedByClient($request, is_numeric($organizationId) ? (int) $organizationId : 0);

        return $this->ok(new WebhookDeliveryResource($redeliver->handle($delivery, CurrentActor::get())));
    }
}
