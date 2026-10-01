<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Enums\WebhookEventType;
use Illuminate\Validation\Rule;

/**
 * `POST …/webhook-endpoints` (app §1.9, public §3.6): `url` (max 1000; the SSRF guard runs in
 * the Action), `event_types[]` (catalogue types, or `["*"]` alone), `description`.
 */
final class StoreWebhookEndpointRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return self::endpointRules(partial: false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function endpointRules(bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'url' => [...$required, 'string', 'max:1000', 'url'],
            'event_types' => [...$required, 'array', 'min:1', 'max:'.count(WebhookEventType::cases())],
            'event_types.*' => ['string', 'distinct', Rule::in([WebhookEventType::WILDCARD, ...WebhookEventType::values()])],
            'description' => [...($partial ? ['sometimes'] : []), 'nullable', 'string', 'max:255'],
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
     * @return array{url: string, event_types: list<string>, description: string|null}
     */
    public function endpointData(): array
    {
        return [
            'url' => trim((string) $this->validated('url')),
            'event_types' => self::normaliseEventTypes($this->validated('event_types')),
            'description' => $this->nullableString('description'),
        ];
    }

    /**
     * `*` stands alone: a list containing it is stored as `["*"]`.
     *
     * @return list<string>
     */
    public static function normaliseEventTypes(mixed $types): array
    {
        $types = array_values(array_filter(is_array($types) ? $types : [], is_string(...)));

        return in_array(WebhookEventType::WILDCARD, $types, true) ? [WebhookEventType::WILDCARD] : $types;
    }
}
