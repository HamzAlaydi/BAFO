<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Enums\WebhookEndpointStatus;
use Illuminate\Validation\Rule;

/**
 * `PATCH …/webhook-endpoints/{endpoint}`: url, event types, description, status (active|disabled).
 */
final class UpdateWebhookEndpointRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...StoreWebhookEndpointRequest::endpointRules(partial: true),
            'status' => ['sometimes', 'required', 'string', Rule::enum(WebhookEndpointStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['event_types.*.in' => self::trans('integrations.validation.event_type')];
    }

    /**
     * @return array{url?: string, event_types?: list<string>, description?: string|null, status?: WebhookEndpointStatus}
     */
    public function endpointData(): array
    {
        $data = [];

        if ($this->has('url')) {
            $data['url'] = trim((string) $this->validated('url'));
        }

        if ($this->has('event_types')) {
            $data['event_types'] = StoreWebhookEndpointRequest::normaliseEventTypes($this->validated('event_types'));
        }

        if ($this->has('description')) {
            $data['description'] = $this->nullableString('description');
        }

        if ($this->has('status')) {
            $data['status'] = WebhookEndpointStatus::from((string) $this->validated('status'));
        }

        return $data;
    }
}
