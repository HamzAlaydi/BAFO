<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

/**
 * `POST /integrations/api-clients/{client}/keys`: `expires_in_days` 1–730, default 365.
 */
final class StoreApiKeyRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('bafo.integrations.api_keys.max_ttl_days')],
        ];
    }

    public function expiresInDays(): int
    {
        $days = $this->validated('expires_in_days');

        return is_numeric($days) ? (int) $days : (int) config('bafo.integrations.api_keys.default_ttl_days');
    }
}
