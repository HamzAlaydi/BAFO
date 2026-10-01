<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\AppV1;

use App\Modules\Integrations\Actions\Webhooks\CreateWebhookEndpoint;
use App\Modules\Integrations\Actions\Webhooks\DeleteWebhookEndpoint;
use App\Modules\Integrations\Actions\Webhooks\RedeliverWebhook;
use App\Modules\Integrations\Actions\Webhooks\RotateWebhookEndpointSecret;
use App\Modules\Integrations\Actions\Webhooks\SendTestWebhook;
use App\Modules\Integrations\Actions\Webhooks\UpdateWebhookEndpoint;
use App\Modules\Integrations\Enums\DeliveryStatus;
use App\Modules\Integrations\Enums\WebhookEventType;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
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
 * Webhook endpoints, the delivery log and redelivery in the dashboard (API.md §1.9,
 * ARCHITECTURE §14.5). The route group applies `integrations.access:api`.
 */
final class WebhookEndpointController extends ApiController
{
    use ResolvesTenant;

    public function eventTypes(): JsonResponse
    {
        return $this->ok(self::catalogue());
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

        return $this->created((new WebhookEndpointResource($issued->endpoint))->withSecret($issued->secret));
    }

    public function show(WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        return $this->ok(new WebhookEndpointResource($endpoint));
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $endpoint, UpdateWebhookEndpoint $update): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        return $this->ok(new WebhookEndpointResource($update->handle($endpoint, $request->endpointData(), CurrentActor::get())));
    }

    public function destroy(WebhookEndpoint $endpoint, DeleteWebhookEndpoint $delete): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        $delete->handle($endpoint, CurrentActor::get());

        return $this->noContent();
    }

    public function test(WebhookEndpoint $endpoint, SendTestWebhook $send): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        return $this->ok(['event_id' => $send->handle($endpoint, CurrentActor::get())->public_id], status: 202);
    }

    public function rotateSecret(WebhookEndpoint $endpoint, RotateWebhookEndpointSecret $rotate): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        $issued = $rotate->handle($endpoint, CurrentActor::get());

        return $this->ok((new WebhookEndpointResource($issued->endpoint))->withSecret($issued->secret));
    }

    public function deliveries(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        $filters = $request->validate(['status' => ['nullable', 'string', Rule::enum(DeliveryStatus::class)]]);

        $deliveries = WebhookDelivery::query()
            ->with('event')
            ->where('webhook_endpoint_id', $endpoint->id)
            ->when(isset($filters['status']), static fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 20, 100));

        return $this->paginated($deliveries, WebhookDeliveryResource::class);
    }

    public function redeliver(WebhookDelivery $delivery, RedeliverWebhook $redeliver): JsonResponse
    {
        $this->authorize('manage', $delivery);

        return $this->ok(new WebhookDeliveryResource($redeliver->handle($delivery, CurrentActor::get())));
    }

    /**
     * The API.md §4.1 catalogue with localised descriptions.
     *
     * @return list<array{type: string, description: string}>
     */
    public static function catalogue(): array
    {
        return array_map(static fn (WebhookEventType $type): array => [
            'type' => $type->value,
            'description' => $type->label(),
        ], WebhookEventType::cases());
    }
}
