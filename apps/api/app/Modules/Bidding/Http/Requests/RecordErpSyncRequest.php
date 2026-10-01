<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

use App\Modules\Bidding\Enums\ErpSyncStatus;

/**
 * Public POST /awards/{award}/erp-sync (API.md §3.5): `status` synced|failed, an optional
 * message and the ERP references to store on the award (`ExternalRef`, `id` is the ERP key).
 */
final class RecordErpSyncRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:synced,failed'],
            'message' => ['nullable', 'string', 'max:500'],
            'external_refs' => ['nullable', 'array', 'max:20'],
            'external_refs.*.system' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+(:[a-z0-9_]+)?$/'],
            'external_refs.*.type' => ['required', 'string', 'max:60'],
            'external_refs.*.id' => ['required', 'string', 'max:120'],
            'external_refs.*.number' => ['nullable', 'string', 'max:120'],
            'external_refs.*.url' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }

    public function syncStatus(): ErpSyncStatus
    {
        return ErpSyncStatus::from((string) $this->validated('status'));
    }

    public function message(): ?string
    {
        return $this->stringOrNull('message');
    }

    /**
     * @return list<array{system: string, type: string, id: string, number: string|null, url: string|null}>
     */
    public function externalRefs(): array
    {
        $refs = [];

        foreach ((array) ($this->validated('external_refs') ?? []) as $ref) {
            if (! is_array($ref)) {
                continue;
            }

            $refs[] = [
                'system' => (string) $ref['system'],
                'type' => (string) $ref['type'],
                'id' => (string) $ref['id'],
                'number' => isset($ref['number']) && is_string($ref['number']) ? $ref['number'] : null,
                'url' => isset($ref['url']) && is_string($ref['url']) ? $ref['url'] : null,
            ];
        }

        return $refs;
    }
}
